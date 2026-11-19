<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('dsn_key')->unique();
            $table->string('environment')->default('production');
            $table->text('discord_webhook_url')->nullable();
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->timestamps();
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('fingerprint')->index();
            $table->string('title');
            $table->string('culprit')->nullable();
            $table->string('level')->default('error');
            $table->string('status')->default('unresolved'); // unresolved, resolved, ignored
            $table->unsignedInteger('occurrence_count')->default(0);
            $table->unsignedInteger('affected_users_count')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->string('resolved_in_release')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'last_seen_at']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->onDelete('cascade');
            $table->longText('payload_json');
            $table->longText('user_context_json')->nullable();
            $table->longText('breadcrumbs_json')->nullable();
            $table->longText('stacktrace_json')->nullable();
            $table->string('release')->nullable();
            $table->string('environment')->nullable();
            $table->timestamps();

            $table->index(['issue_id', 'created_at']);
        });

        Schema::create('performance_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('route_name')->nullable();
            $table->string('method')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedInteger('query_count')->default(0);
            $table->longText('slow_queries_json')->nullable();
            $table->longText('n_plus_one_warnings_json')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_traces');
        Schema::dropIfExists('events');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('projects');
    }
};
