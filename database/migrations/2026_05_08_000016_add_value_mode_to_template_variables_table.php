<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            // How this variable behaves during generation
            // ask_each_time    — always shown, user must fill it
            // default_editable — pre-filled with default_value, user can edit
            // fixed_hidden     — hidden from form, system uses fixed_value automatically
            $table->string('value_mode', 20)->default('ask_each_time')->after('approval_status');

            // The locked value used when value_mode = fixed_hidden
            $table->text('fixed_value')->nullable()->after('value_mode');

            // Traceability: which generation first set this fixed value
            $table->unsignedBigInteger('fixed_value_set_by_generation_id')->nullable()->after('fixed_value');
            $table->unsignedBigInteger('fixed_value_set_by_user_id')->nullable()->after('fixed_value_set_by_generation_id');
            $table->timestamp('fixed_value_set_at')->nullable()->after('fixed_value_set_by_user_id');

            // Whether to show fixed fields read-only in the form (future: fixed_visible_readonly)
            $table->boolean('show_when_fixed')->default(false)->after('fixed_value_set_at');

            // AI's suggestion for which mode this field should use
            $table->string('ai_suggested_mode', 20)->nullable()->after('show_when_fixed');
            $table->text('ai_suggested_mode_reason')->nullable()->after('ai_suggested_mode');

            // Whether the user has confirmed the mode (vs just accepting AI default)
            $table->boolean('user_confirmed_mode')->default(false)->after('ai_suggested_mode_reason');

            // Whether this field was flagged as potentially sensitive (personal info)
            $table->boolean('is_sensitive_flag')->default(false)->after('user_confirmed_mode');

            $table->index('value_mode');
        });
    }

    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->dropIndex(['value_mode']);
            $table->dropColumn([
                'value_mode', 'fixed_value',
                'fixed_value_set_by_generation_id', 'fixed_value_set_by_user_id',
                'fixed_value_set_at', 'show_when_fixed',
                'ai_suggested_mode', 'ai_suggested_mode_reason',
                'user_confirmed_mode', 'is_sensitive_flag',
            ]);
        });
    }
};
