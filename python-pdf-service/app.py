"""
RepetitiveDocs PDF Microservice
================================
Handles PDF text extraction (pdfplumber) and overlay generation (reportlab).

Why Python instead of PHP:
  pdfplumber reads the PDF content stream and returns EXACT word-level bounding
  boxes in PDF points. No coordinate system mismatches, no approximations.
  reportlab draws text overlays at those exact coordinates.

Endpoints:
  POST /analyze       — extract full text + word positions from a PDF
  POST /find-positions — given a PDF + list of phrases, return exact bboxes
  POST /generate      — overlay new values on a PDF at stored bboxes

Run:  gunicorn -w 2 -b 127.0.0.1:5050 app:app
"""

import io
import base64
import json
import re
import tempfile
import os

import pdfplumber
from reportlab.pdfgen import canvas as rl_canvas
from reportlab.lib.colors import HexColor
from pypdf import PdfReader, PdfWriter
from flask import Flask, request, jsonify, Response

app = Flask(__name__)


# ── Helpers ──────────────────────────────────────────────────────────────────

def _words_from_page(page):
    """Extract words with font metadata from a pdfplumber page."""
    try:
        return page.extract_words(
            x_tolerance=5,
            y_tolerance=3,
            keep_blank_chars=False,
            extra_attrs=["fontname", "size"],
        )
    except Exception:
        return []


def _dominant_word(words):
    """Return the word with the largest font size from a list."""
    try:
        return max(words, key=lambda w: float(w.get("size") or 0))
    except (ValueError, TypeError):
        return words[0] if words else {}


def _bbox_from_words(words, page_width, page_height):
    """Build a normalised bbox dict from a list of word dicts."""
    x0 = min(w["x0"] for w in words)
    top = min(w["top"] for w in words)
    x1 = max(w["x1"] for w in words)
    bottom = max(w["bottom"] for w in words)
    dom = _dominant_word(words)
    font_name = dom.get("fontname") or ""
    return {
        "x0": x0,
        "y0": top,       # distance from TOP of page
        "x1": x1,
        "y1": bottom,    # distance from TOP of page (y1 > y0)
        "x_pct": x0 / page_width,
        "y_pct": top / page_height,
        "w_pct": (x1 - x0) / page_width,
        "h_pct": (bottom - top) / page_height,
        "font_name": font_name,
        "font_size": float(dom.get("size") or 10),
        "is_bold": "bold" in font_name.lower(),
        "page_width": page_width,
        "page_height": page_height,
    }


def _normalize(text):
    return re.sub(r"\s+", " ", text or "").strip().lower()


def _find_phrase_in_words(words, phrase, page_width, page_height):
    """
    Find all occurrences of `phrase` in a sorted word list (one page).
    Groups words into lines first, then scans for the phrase across
    consecutive words on the same line (within 5pt vertical tolerance).
    Returns a list of bbox dicts.
    """
    phrase_norm = _normalize(phrase)
    if not phrase_norm or len(phrase_norm) < 2:
        return []

    # Group words into lines by vertical proximity
    lines = []
    for word in sorted(words, key=lambda w: (w.get("top", 0), w.get("x0", 0))):
        placed = False
        for line in lines:
            if abs(word.get("top", 0) - line[0].get("top", 0)) <= 5:
                line.append(word)
                placed = True
                break
        if not placed:
            lines.append([word])

    positions = []
    phrase_tokens = phrase_norm.split()
    n = len(phrase_tokens)

    for line in lines:
        line = sorted(line, key=lambda w: w.get("x0", 0))
        texts = [_normalize(w.get("text", "")) for w in line]

        for i in range(len(line) - n + 1):
            chunk_texts = texts[i : i + n]
            chunk_joined = " ".join(chunk_texts)

            if chunk_joined == phrase_norm or phrase_norm in chunk_joined:
                chunk_words = line[i : i + n]
                positions.append(_bbox_from_words(chunk_words, page_width, page_height))

    return positions


# ── Endpoints ─────────────────────────────────────────────────────────────────

@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/analyze", methods=["POST"])
def analyze():
    """
    Extract full text + word-level positions from a PDF.

    Input:  multipart file field 'pdf'
    Output: {
        pages: [{page_number, width, height, text, words: [...]}],
        full_text: "..."
    }
    """
    if "pdf" not in request.files:
        return jsonify({"error": "No 'pdf' file in request"}), 400

    pdf_file = request.files["pdf"]
    pdf_bytes = pdf_file.read()

    try:
        pages = []
        with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
            for i, page in enumerate(pdf.pages):
                words = _words_from_page(page)
                page_data = {
                    "page_number": i + 1,
                    "width": float(page.width),
                    "height": float(page.height),
                    "text": page.extract_text() or "",
                    "words": [
                        {
                            "text": w.get("text", ""),
                            "x0": float(w.get("x0", 0)),
                            "y0": float(w.get("top", 0)),
                            "x1": float(w.get("x1", 0)),
                            "y1": float(w.get("bottom", 0)),
                            "font_name": w.get("fontname") or "",
                            "font_size": float(w.get("size") or 10),
                        }
                        for w in words
                    ],
                }
                pages.append(page_data)

        full_text = "\n\n".join(p["text"] for p in pages)
        return jsonify({"pages": pages, "full_text": full_text})

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route("/find-positions", methods=["POST"])
def find_positions():
    """
    Find exact bounding boxes for each phrase in the PDF.

    Input:  multipart 'pdf' file  +  JSON field 'terms' (list of strings)
    Output: { "term_text": [ {page, x0, y0, x1, y1, x_pct, y_pct, ...}, ... ], ... }
    """
    if "pdf" not in request.files:
        return jsonify({"error": "No 'pdf' file in request"}), 400

    pdf_bytes = request.files["pdf"].read()
    terms_raw = request.form.get("terms", "[]")
    try:
        terms = json.loads(terms_raw)
    except json.JSONDecodeError:
        return jsonify({"error": "Invalid JSON in 'terms' field"}), 400

    if not terms:
        return jsonify({})

    try:
        results = {term: [] for term in terms}

        with pdfplumber.open(io.BytesIO(pdf_bytes)) as pdf:
            for i, page in enumerate(pdf.pages):
                words = _words_from_page(page)
                if not words:
                    continue
                pw, ph = float(page.width), float(page.height)
                for term in terms:
                    found = _find_phrase_in_words(words, term, pw, ph)
                    for pos in found:
                        pos["page"] = i + 1
                    results[term].extend(found)

        return jsonify(results)

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route("/generate", methods=["POST"])
def generate():
    """
    Overlay new values on a PDF template at exact bounding box coordinates.

    Input JSON: {
        "pdf_b64": "<base64 PDF bytes>",
        "replacements": [
            {
                "page":       1,
                "x0":         100.0,   # PDF points from left
                "y0":         200.0,   # PDF points from TOP of page
                "x1":         300.0,
                "y1":         215.0,   # PDF points from TOP (y1 > y0)
                "new_text":   "Juan Dela Cruz",
                "font_size":  12.0,
                "is_bold":    false,
                "text_align": "L",     # L | C | R
                "font_color": "#000000"
            }
        ]
    }
    Output: PDF bytes (application/pdf)
    """
    data = request.get_json(silent=True)
    if not data:
        return jsonify({"error": "JSON body required"}), 400

    pdf_b64 = data.get("pdf_b64")
    replacements = data.get("replacements", [])

    if not pdf_b64:
        return jsonify({"error": "pdf_b64 required"}), 400

    try:
        pdf_bytes = base64.b64decode(pdf_b64)
    except Exception:
        return jsonify({"error": "Invalid base64 in pdf_b64"}), 400

    try:
        reader = PdfReader(io.BytesIO(pdf_bytes))
        writer = PdfWriter()

        # Group replacements by page (1-indexed)
        by_page = {}
        for r in replacements:
            p = int(r.get("page", 1))
            by_page.setdefault(p, []).append(r)

        for i, page in enumerate(reader.pages):
            page_num = i + 1
            page_w = float(page.mediabox.width)
            page_h = float(page.mediabox.height)

            page_replacements = by_page.get(page_num, [])

            if page_replacements:
                # Build an overlay PDF page using reportlab
                packet = io.BytesIO()
                c = rl_canvas.Canvas(packet, pagesize=(page_w, page_h))

                for r in page_replacements:
                    x0 = float(r.get("x0", 0))
                    y0 = float(r.get("y0", 0))   # from TOP
                    x1 = float(r.get("x1", x0 + 50))
                    y1 = float(r.get("y1", y0 + 12))  # from TOP (y1 > y0)
                    text = str(r.get("new_text", ""))
                    font_size = float(r.get("font_size", 10))
                    is_bold = bool(r.get("is_bold", False))
                    align = str(r.get("text_align", "L")).upper()
                    hex_color = str(r.get("font_color", "#000000"))

                    width = x1 - x0
                    height = y1 - y0

                    # Convert from pdfplumber top-based to reportlab bottom-based coords
                    # reportlab y=0 is at the bottom of the page
                    rl_bottom = page_h - y1  # bottom of text area in reportlab space
                    rl_top = page_h - y0     # top of text area

                    # Erase original text (white rect with small padding)
                    pad = 2.0
                    c.setFillColor(HexColor("#FFFFFF"))
                    c.rect(
                        x0 - pad,
                        rl_bottom - pad,
                        width + pad * 2,
                        height + pad * 2,
                        fill=1,
                        stroke=0,
                    )

                    # Parse font color
                    try:
                        fill_color = HexColor(hex_color)
                    except Exception:
                        fill_color = HexColor("#000000")

                    c.setFillColor(fill_color)

                    # Font
                    font_name = "Helvetica-Bold" if is_bold else "Helvetica"
                    c.setFont(font_name, font_size)

                    # Text baseline: roughly at the bottom of the text area
                    text_y = rl_bottom

                    # Transliterate non-latin chars (reportlab core fonts are latin-only)
                    safe_text = _safe_latin(text)

                    if align == "C":
                        c.drawCentredString(x0 + width / 2, text_y, safe_text)
                    elif align == "R":
                        c.drawRightString(x1, text_y, safe_text)
                    else:
                        c.drawString(x0, text_y, safe_text)

                c.save()
                packet.seek(0)

                overlay_reader = PdfReader(packet)
                overlay_page = overlay_reader.pages[0]
                page.merge_page(overlay_page)

            writer.add_page(page)

        output = io.BytesIO()
        writer.write(output)
        pdf_out = output.getvalue()

        return Response(
            pdf_out,
            mimetype="application/pdf",
            headers={"Content-Disposition": "attachment; filename=generated.pdf"},
        )

    except Exception as e:
        return jsonify({"error": str(e)}), 500


def _safe_latin(text):
    """Transliterate UTF-8 text to latin-1 for reportlab core fonts."""
    try:
        return text.encode("latin-1", errors="replace").decode("latin-1")
    except Exception:
        return text


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5050, debug=False)
