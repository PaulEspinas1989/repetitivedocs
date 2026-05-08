<?php

namespace Tests\Feature;

use App\Models\TemplateVariable;
use App\Services\DocumentGenerationService;
use App\Services\GenerationValueResolverService;
use Tests\TestCase;

/**
 * Tests for variable detection logic and style-preserving generation.
 *
 * Most of these are unit-level tests on services and models
 * that can run without a real database connection.
 */
class VariableDetectionTest extends TestCase
{
    // ── Casing detection ─────────────────────────────────────────────

    /** @test */
    public function it_detects_uppercase_casing_pattern(): void
    {
        $service = $this->makeDetectionService();

        $this->assertSame('uppercase', $this->casingPattern($service, 'JUAN DELA CRUZ'));
        $this->assertSame('uppercase', $this->casingPattern($service, 'HON. JUAN DELA CRUZ'));
        $this->assertSame('uppercase', $this->casingPattern($service, 'MUNICIPALITY OF MILAGROS'));
    }

    /** @test */
    public function it_detects_titlecase_casing_pattern(): void
    {
        $service = $this->makeDetectionService();

        $this->assertSame('titlecase', $this->casingPattern($service, 'Juan Dela Cruz'));
        $this->assertSame('titlecase', $this->casingPattern($service, 'Maria Santos'));
        $this->assertSame('titlecase', $this->casingPattern($service, 'Municipality Of Milagros'));
    }

    /** @test */
    public function it_detects_mixed_casing_pattern(): void
    {
        $service = $this->makeDetectionService();

        $this->assertSame('mixed', $this->casingPattern($service, 'juan dela Cruz')); // inconsistent
    }

    // ── Generation: casing preservation ──────────────────────────────

    /** @test */
    public function generation_applies_uppercase_casing_to_replacement(): void
    {
        $service = app(DocumentGenerationService::class);

        $pos = [
            'casing_pattern' => 'uppercase',
            'prefix_text'    => '',
            'suffix_text'    => '',
            'original_text'  => 'JUAN DELA CRUZ',
        ];

        $result = $this->invokeApplyCasing($service, 'Maria Santos', $pos);

        $this->assertSame('MARIA SANTOS', $result);
    }

    /** @test */
    public function generation_applies_titlecase_to_replacement(): void
    {
        $service = app(DocumentGenerationService::class);

        $pos = [
            'casing_pattern' => 'titlecase',
            'prefix_text'    => '',
            'suffix_text'    => '',
            'original_text'  => 'Juan Dela Cruz',
        ];

        $result = $this->invokeApplyCasing($service, 'maria santos', $pos);

        $this->assertSame('Maria Santos', $result);
    }

    /** @test */
    public function generation_preserves_mixed_casing_as_is(): void
    {
        $service = app(DocumentGenerationService::class);

        $pos = [
            'casing_pattern' => 'mixed',
            'prefix_text'    => '',
            'suffix_text'    => '',
            'original_text'  => 'some mixed text',
        ];

        $result = $this->invokeApplyCasing($service, 'Maria Santos', $pos);

        // Mixed → trust user input, no transformation
        $this->assertSame('Maria Santos', $result);
    }

    // ── Generation: prefix/suffix re-application ──────────────────────

    /** @test */
    public function generation_reapplies_hon_prefix(): void
    {
        $service = app(DocumentGenerationService::class);

        $pos = [
            'prefix_text'    => 'HON.',
            'suffix_text'    => '',
            'casing_pattern' => 'uppercase',
            'original_text'  => 'HON. JUAN DELA CRUZ',
        ];

        // User enters clean name: "Maria Santos"
        // After casing: "MARIA SANTOS"
        // After prefix: "HON. MARIA SANTOS"
        $afterCasing = $this->invokeApplyCasing($service, 'Maria Santos', $pos);
        $result = $this->invokeApplyPrefixSuffix($service, $afterCasing, $pos);

        $this->assertSame('HON. MARIA SANTOS', $result);
    }

    /** @test */
    public function generation_reapplies_mayor_prefix_with_title_case(): void
    {
        $service = app(DocumentGenerationService::class);

        $pos = [
            'prefix_text'    => 'Mayor',
            'suffix_text'    => '',
            'casing_pattern' => 'titlecase',
            'original_text'  => 'Mayor Juan Dela Cruz',
        ];

        $afterCasing = $this->invokeApplyCasing($service, 'maria santos', $pos);
        $result = $this->invokeApplyPrefixSuffix($service, $afterCasing, $pos);

        $this->assertSame('Mayor Maria Santos', $result);
    }

    /** @test */
    public function generation_skips_empty_prefix(): void
    {
        $service = app(DocumentGenerationService::class);

        $pos = [
            'prefix_text'    => '',
            'suffix_text'    => '',
            'casing_pattern' => 'titlecase',
            'original_text'  => 'Juan Dela Cruz',
        ];

        $afterCasing = $this->invokeApplyCasing($service, 'maria santos', $pos);
        $result = $this->invokeApplyPrefixSuffix($service, $afterCasing, $pos);

        $this->assertSame('Maria Santos', $result);
    }

    // ── Value mode helpers on TemplateVariable ─────────────────────

    /** @test */
    public function template_variable_fixed_mode_is_hidden_from_form(): void
    {
        $var = new TemplateVariable(['value_mode' => TemplateVariable::MODE_FIXED, 'show_when_fixed' => false]);

        $this->assertTrue($var->isFixed());
        $this->assertTrue($var->isHiddenFromForm());
        $this->assertFalse($var->requiresUserInput());
    }

    /** @test */
    public function template_variable_default_mode_requires_user_input(): void
    {
        $var = new TemplateVariable(['value_mode' => TemplateVariable::MODE_DEFAULT]);

        $this->assertTrue($var->isDefault());
        $this->assertFalse($var->isFixed());
        $this->assertTrue($var->requiresUserInput());
        $this->assertFalse($var->isHiddenFromForm());
    }

    /** @test */
    public function template_variable_ask_mode_requires_user_input(): void
    {
        $var = new TemplateVariable(['value_mode' => TemplateVariable::MODE_ASK]);

        $this->assertTrue($var->isAskEachTime());
        $this->assertFalse($var->isFixed());
        $this->assertTrue($var->requiresUserInput());
    }

    /** @test */
    public function sensitive_detection_flags_email_type(): void
    {
        $var = new TemplateVariable(['type' => 'email', 'label' => 'Email Address']);

        $this->assertTrue($var->looksLikeSensitive());
    }

    /** @test */
    public function sensitive_detection_flags_name_label(): void
    {
        $var = new TemplateVariable(['type' => 'text', 'label' => 'Recipient Name']);

        $this->assertTrue($var->looksLikeSensitive());
    }

    // ── GenerationValueResolverService ────────────────────────────────

    /** @test */
    public function resolver_uses_fixed_value_over_empty_user_input(): void
    {
        $resolver = app(GenerationValueResolverService::class);

        $var = new TemplateVariable([
            'name'       => 'mayor_name',
            'value_mode' => TemplateVariable::MODE_FIXED,
            'fixed_value' => 'JUAN DELA CRUZ',
        ]);

        $result = $resolver->resolveOne($var, [], []);

        $this->assertSame('JUAN DELA CRUZ', $result);
    }

    /** @test */
    public function resolver_user_input_overrides_default(): void
    {
        $resolver = app(GenerationValueResolverService::class);

        $var = new TemplateVariable([
            'name'          => 'mayor_name',
            'value_mode'    => TemplateVariable::MODE_DEFAULT,
            'default_value' => 'Default Mayor',
        ]);

        $result = $resolver->resolveOne($var, ['mayor_name' => 'Maria Santos'], []);

        $this->assertSame('Maria Santos', $result);
    }

    /** @test */
    public function resolver_one_time_override_wins_over_fixed(): void
    {
        $resolver = app(GenerationValueResolverService::class);

        $var = new TemplateVariable([
            'name'        => 'mayor_name',
            'value_mode'  => TemplateVariable::MODE_FIXED,
            'fixed_value' => 'JUAN DELA CRUZ',
        ]);

        $result = $resolver->resolveOne($var, [], ['mayor_name' => 'Maria Santos']);

        $this->assertSame('Maria Santos', $result);
    }

    // ── Private test helpers ──────────────────────────────────────────

    private function makeDetectionService(): \App\Services\VariableDetectionService
    {
        return app(\App\Services\VariableDetectionService::class);
    }

    private function casingPattern(\App\Services\VariableDetectionService $service, string $text): string
    {
        return $this->callPrivate($service, 'detectCasingPattern', [$text]);
    }

    private function invokeApplyCasing(DocumentGenerationService $service, string $value, array $pos): string
    {
        return $this->callPrivate($service, 'applyCasingFromOccurrence', [$value, $pos]);
    }

    private function invokeApplyPrefixSuffix(DocumentGenerationService $service, string $value, array $pos): string
    {
        return $this->callPrivate($service, 'applyPrefixSuffix', [$value, $pos]);
    }

    private function callPrivate(object $object, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }
}
