<?php

namespace Tests\Feature\Community;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Verifica que down()/up() de 2027_01_01_000440 funcionan de punta a punta
 * -- checklist del skill migration-conventions -- SIN tocar la base de
 * desarrollo real (liceo_innovarte). Mismo patrón de guard que
 * SubmissionAttachmentsMigrationTest: corre exclusivamente contra
 * liceo_innovarte_testing (fijada por phpunit.xml/.env.testing), nunca
 * fuera de un test real. NUNCA copiar este bloque up()/down() fuera de un
 * test -- ver docblock de SubmissionAttachmentsMigrationTest para el
 * motivo completo.
 */
class PrivateChatMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_down_then_up_recreates_both_tables_cleanly(): void
    {
        abort_unless(
            config('database.connections.mysql.database') === 'liceo_innovarte_testing',
            500,
            'Este test solo puede correr contra la base de testing -- ver docblock de esta clase.'
        );

        $migration = require database_path('migrations/2027_01_01_000440_create_private_chat_tables.php');

        $this->assertTrue(Schema::hasTable('private_chat_threads'));
        $this->assertTrue(Schema::hasTable('private_chat_messages'));

        $migration->down();

        $this->assertFalse(Schema::hasTable('private_chat_messages'));
        $this->assertFalse(Schema::hasTable('private_chat_threads'));

        $migration->up();

        $this->assertTrue(Schema::hasTable('private_chat_threads'));
        $this->assertTrue(Schema::hasTable('private_chat_messages'));
        $this->assertTrue(Schema::hasColumn('private_chat_threads', 'uuid'));
        $this->assertTrue(Schema::hasColumn('private_chat_messages', 'is_hidden'));
    }
}
