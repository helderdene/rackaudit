<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds 'restored' to the action enum to support tracking
     * of soft-deleted model restorations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL: Modify the enum column to add 'restored'
            DB::statement("ALTER TABLE `activity_logs` MODIFY `action` ENUM('created', 'updated', 'deleted', 'restored') NOT NULL");
        } elseif ($driver === 'sqlite') {
            // SQLite: Recreate table with new enum constraint
            // SQLite doesn't support altering column constraints directly
            // We need to recreate the table structure

            // First, drop the old constraint by recreating the table
            DB::statement('PRAGMA foreign_keys=off;');

            // Create a new table with the updated constraint
            DB::statement("
                CREATE TABLE activity_logs_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject_type VARCHAR(255) NOT NULL,
                    subject_id INTEGER NOT NULL,
                    causer_id INTEGER,
                    action VARCHAR(255) CHECK(action IN ('created', 'updated', 'deleted', 'restored')) NOT NULL,
                    old_values TEXT,
                    new_values TEXT,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (causer_id) REFERENCES users(id) ON DELETE SET NULL
                );
            ");

            // Copy data from old table
            DB::statement('INSERT INTO activity_logs_new SELECT * FROM activity_logs;');

            // Drop old table
            DB::statement('DROP TABLE activity_logs;');

            // Rename new table
            DB::statement('ALTER TABLE activity_logs_new RENAME TO activity_logs;');

            // Recreate indexes
            DB::statement('CREATE INDEX activity_logs_subject_type_subject_id_index ON activity_logs (subject_type, subject_id);');
            DB::statement('CREATE INDEX activity_logs_causer_id_index ON activity_logs (causer_id);');

            DB::statement('PRAGMA foreign_keys=on;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL: Revert the enum column
            DB::statement("ALTER TABLE `activity_logs` MODIFY `action` ENUM('created', 'updated', 'deleted') NOT NULL");
        } elseif ($driver === 'sqlite') {
            // SQLite: Recreate table with original constraint
            DB::statement('PRAGMA foreign_keys=off;');

            DB::statement("
                CREATE TABLE activity_logs_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    subject_type VARCHAR(255) NOT NULL,
                    subject_id INTEGER NOT NULL,
                    causer_id INTEGER,
                    action VARCHAR(255) CHECK(action IN ('created', 'updated', 'deleted')) NOT NULL,
                    old_values TEXT,
                    new_values TEXT,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    created_at DATETIME,
                    updated_at DATETIME,
                    FOREIGN KEY (causer_id) REFERENCES users(id) ON DELETE SET NULL
                );
            ");

            // Copy data (excluding any 'restored' actions which would fail)
            DB::statement("INSERT INTO activity_logs_new SELECT * FROM activity_logs WHERE action != 'restored';");

            DB::statement('DROP TABLE activity_logs;');
            DB::statement('ALTER TABLE activity_logs_new RENAME TO activity_logs;');

            DB::statement('CREATE INDEX activity_logs_subject_type_subject_id_index ON activity_logs (subject_type, subject_id);');
            DB::statement('CREATE INDEX activity_logs_causer_id_index ON activity_logs (causer_id);');

            DB::statement('PRAGMA foreign_keys=on;');
        }
    }
};
