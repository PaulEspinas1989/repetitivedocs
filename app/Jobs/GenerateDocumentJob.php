<?php

namespace App\Jobs;

use App\Models\GeneratedDocument;
use App\Models\Template;
use App\Models\TemplateVariable;
use App\Services\DocumentGenerationService;
use App\Services\GenerationValueResolverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 1;

    public function __construct(
        public readonly GeneratedDocument $pending,
        public readonly Template $template,
        public readonly array $overrides,
        public readonly array $keepAsConstant,
    ) {}

    public function handle(
        DocumentGenerationService $generator,
        GenerationValueResolverService $resolver,
    ): void {
        // $pending->variable_values holds pre-formatted user values (dates already formatted,
        // currency stripped) — stored by FillableFormController before dispatching.
        $userValues = $this->pending->variable_values ?? [];

        try {
            // Load relationships needed for generation
            $doc = $this->template->uploadedDocument;
            if ($doc && $doc->isPdf()) {
                $this->template->load(['approvedVariables.activeOccurrences']);
            } else {
                $this->template->load(['approvedVariables']);
            }

            $resolvedValues = $resolver->resolve($this->template, $userValues, $this->overrides);

            // Generate the actual file using the resolved values
            $fileInfo = $generator->generateFile($this->template, $resolvedValues);

            // PDF overlay creates an orphan GeneratedDocument internally — delete it
            if (!empty($fileInfo['_orphan_id'])) {
                GeneratedDocument::find($fileInfo['_orphan_id'])?->delete();
            }

            // Save keep-as-constant values after successful generation
            foreach ($this->template->approvedVariables as $var) {
                if (empty($this->keepAsConstant[$var->name])) {
                    continue;
                }
                $valueToSave = $userValues[$var->name] ?? $this->overrides[$var->name] ?? null;
                if ($valueToSave === null || $valueToSave === '') {
                    continue;
                }
                $var->update([
                    'value_mode'                       => TemplateVariable::MODE_FIXED,
                    'fixed_value'                      => $valueToSave,
                    'fixed_value_set_by_user_id'       => $this->pending->user_id,
                    'fixed_value_set_at'               => now(),
                    'fixed_value_set_by_generation_id' => $this->pending->id,
                    'user_confirmed_mode'              => true,
                    'show_when_fixed'                  => false,
                ]);
            }

            // Record per-field traceability
            $resolver->recordValues($this->pending, $this->template, $userValues, $this->overrides);

            // Mark as ready and attach the file
            $this->pending->update([
                'file_path'       => $fileInfo['file_path'],
                'file_name'       => $fileInfo['file_name'],
                'status'          => 'ready',
                'variable_values' => $resolvedValues,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateDocumentJob failed', [
                'generated_id' => $this->pending->id,
                'template_id'  => $this->template->id,
                'error'        => $e->getMessage(),
            ]);
            $this->pending->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
