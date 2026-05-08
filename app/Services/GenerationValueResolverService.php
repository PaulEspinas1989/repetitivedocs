<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\GeneratedDocumentValue;
use App\Models\Template;
use App\Models\TemplateVariable;

/**
 * Resolves the final value for each template variable during document generation.
 *
 * Priority order (highest to lowest):
 *   1. One-time override (user explicitly overriding a fixed field for this generation)
 *   2. User-submitted form input
 *   3. Fixed value (value_mode = fixed_hidden)
 *   4. Default value (value_mode = default_editable, no submission)
 *   5. Empty (triggers preflight/validation warning)
 *
 * Also records per-field values in generated_document_values for traceability.
 */
class GenerationValueResolverService
{
    /**
     * Resolve all values for a generation, returning a keyed array
     * of [ variable_name => final_value ] for every approved variable.
     *
     * @param  Template $template      The template being generated from
     * @param  array    $userValues    Values submitted by the user (keyed by variable name)
     * @param  array    $overrides     One-time overrides for fixed fields (keyed by variable name)
     * @return array                   [ variable_name => resolved_value ]
     */
    public function resolve(
        Template $template,
        array $userValues,
        array $overrides = []
    ): array {
        $resolved = [];

        foreach ($template->approvedVariables as $var) {
            $resolved[$var->name] = $this->resolveOne($var, $userValues, $overrides);
        }

        return $resolved;
    }

    /**
     * Resolve the value for a single variable and determine its source.
     * Returns [ 'value' => string|null, 'source' => string, 'mode' => string ]
     */
    public function resolveOne(
        TemplateVariable $var,
        array $userValues,
        array $overrides = []
    ): ?string {
        return $this->resolveWithMeta($var, $userValues, $overrides)['value'];
    }

    /**
     * Resolve with full metadata (value + source + mode).
     */
    public function resolveWithMeta(
        TemplateVariable $var,
        array $userValues,
        array $overrides = []
    ): array {
        $name = $var->name;

        // 1. One-time override (user explicitly overrides a fixed field for this generation only)
        if (isset($overrides[$name]) && $overrides[$name] !== '') {
            return [
                'value'  => $overrides[$name],
                'source' => GeneratedDocumentValue::SOURCE_ONE_TIME,
                'mode'   => $var->value_mode ?? TemplateVariable::MODE_ASK,
            ];
        }

        // 2. User-submitted form value (for ask_each_time and default_editable)
        if (isset($userValues[$name]) && $userValues[$name] !== '') {
            return [
                'value'  => $userValues[$name],
                'source' => GeneratedDocumentValue::SOURCE_USER_INPUT,
                'mode'   => $var->value_mode ?? TemplateVariable::MODE_ASK,
            ];
        }

        // 3. Fixed value — automatically applied, no user input needed
        if ($var->isFixed() && $var->fixed_value !== null && $var->fixed_value !== '') {
            return [
                'value'  => $var->fixed_value,
                'source' => GeneratedDocumentValue::SOURCE_FIXED,
                'mode'   => TemplateVariable::MODE_FIXED,
            ];
        }

        // 4. Default editable — user didn't change it, use the default
        if ($var->isDefault() && $var->default_value !== null && $var->default_value !== '') {
            return [
                'value'  => $var->default_value,
                'source' => GeneratedDocumentValue::SOURCE_DEFAULT,
                'mode'   => TemplateVariable::MODE_DEFAULT,
            ];
        }

        // 5. Empty — will trigger validation errors if field is required
        return [
            'value'  => null,
            'source' => GeneratedDocumentValue::SOURCE_USER_INPUT,
            'mode'   => $var->value_mode ?? TemplateVariable::MODE_ASK,
        ];
    }

    /**
     * Record per-field values for a completed generation.
     * Call this after successful generation so history is immutable.
     */
    public function recordValues(
        GeneratedDocument $generated,
        Template $template,
        array $userValues,
        array $overrides = []
    ): void {
        foreach ($template->approvedVariables as $var) {
            $meta = $this->resolveWithMeta($var, $userValues, $overrides);

            GeneratedDocumentValue::create([
                'generated_document_id'   => $generated->id,
                'template_variable_id'    => $var->id,
                'template_id'             => $template->id,
                'workspace_id'            => $template->workspace_id,
                'final_value_used'        => $meta['value'],
                'submitted_value'         => $userValues[$var->name] ?? null,
                'value_source'            => $meta['source'],
                'value_mode_at_generation' => $meta['mode'],
                'was_fixed_at_generation' => $meta['source'] === GeneratedDocumentValue::SOURCE_FIXED,
                'was_default_at_generation' => $meta['source'] === GeneratedDocumentValue::SOURCE_DEFAULT,
            ]);
        }
    }

    /**
     * Build AI suggestions for value modes based on the variable metadata
     * and the values from the first generation.
     *
     * Returns array of [ variable_name => ['suggested_mode', 'reason', 'sensitive'] ]
     */
    public function suggestModes(Template $template, array $generationValues): array
    {
        $suggestions = [];

        foreach ($template->approvedVariables as $var) {
            $value = $generationValues[$var->name] ?? '';
            $suggestions[$var->name] = $this->suggestModeForVariable($var, $value);
        }

        return $suggestions;
    }

    private function suggestModeForVariable(TemplateVariable $var, ?string $value): array
    {
        $label      = mb_strtolower($var->label ?? '');
        $type       = $var->type ?? 'text';
        $entityRole = $var->entity_role ?? '';
        $semType    = $var->semantic_type ?? '';
        $sensitive  = $var->looksLikeSensitive();

        // Dates always change
        if ($type === 'date') {
            return [
                'suggested_mode' => TemplateVariable::MODE_ASK,
                'reason'         => 'Dates usually change for every document.',
                'sensitive'      => false,
            ];
        }

        // Personal contact info — always ask
        if (in_array($type, ['email', 'phone'], true)) {
            return [
                'suggested_mode' => TemplateVariable::MODE_ASK,
                'reason'         => 'Contact information usually changes per document.',
                'sensitive'      => true,
            ];
        }

        // Amounts / numbers — always ask
        if (in_array($type, ['currency', 'number'], true)) {
            return [
                'suggested_mode' => TemplateVariable::MODE_ASK,
                'reason'         => 'Amounts usually change for every document.',
                'sensitive'      => false,
            ];
        }

        // Signatory/mayor/official roles — likely fixed
        if (in_array($entityRole, ['mayor_signatory', 'signatory', 'approver', 'certifier'], true)) {
            return [
                'suggested_mode' => TemplateVariable::MODE_FIXED,
                'reason'         => 'Official titles and signatory names often stay the same.',
                'sensitive'      => false,
            ];
        }

        // Name fields — generally ask (personal)
        if ($semType === 'person_name' || str_contains($label, 'recipient') || str_contains($label, 'name of')) {
            return [
                'suggested_mode' => TemplateVariable::MODE_ASK,
                'reason'         => 'Recipient names usually change for every document.',
                'sensitive'      => $sensitive,
            ];
        }

        // Organization names — likely fixed or default
        if ($semType === 'org_name' || str_contains($label, 'municipality') || str_contains($label, 'province') || str_contains($label, 'office')) {
            return [
                'suggested_mode' => TemplateVariable::MODE_FIXED,
                'reason'         => 'Organization and location names usually stay the same.',
                'sensitive'      => false,
            ];
        }

        // Position/title fields — likely fixed
        if (str_contains($label, 'position') || str_contains($label, 'title') || str_contains($label, 'designation')) {
            return [
                'suggested_mode' => TemplateVariable::MODE_FIXED,
                'reason'         => 'Positions and titles often stay the same across documents.',
                'sensitive'      => false,
            ];
        }

        // Default: ask every time for unknown fields
        return [
            'suggested_mode' => TemplateVariable::MODE_ASK,
            'reason'         => 'This value may change for each document.',
            'sensitive'      => $sensitive,
        ];
    }
}
