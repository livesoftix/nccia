<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            // Remove auto-generated enquiry_number constraint (make nullable)
            $table->string('enquiry_number')->nullable()->change();

            // Witness section
            $table->string('witness_name')->nullable();
            $table->string('witness_cnic')->nullable();
            $table->string('witness_nationality')->nullable();
            $table->string('witness_passport')->nullable();
            $table->text('witness_address')->nullable();
            $table->string('witness_attachment')->nullable();

            // Notice section
            $table->string('notice_number')->nullable();
            $table->string('notice_type')->nullable();
            $table->string('notice_receiver_name')->nullable();
            $table->date('notice_date')->nullable();
            $table->string('notice_via')->nullable(); // whatsapp, sms, email, phone, fax, postal, call
            $table->string('notice_person_type')->nullable(); // complainant, accused, witness
            $table->text('notice_description')->nullable();
            $table->text('notice_address')->nullable();
            $table->string('notice_phone')->nullable();
            $table->boolean('notice_served')->default(false);
            $table->datetime('notice_served_at')->nullable();
            $table->integer('notice_count')->default(0);

            // Technical & Forensic Reports
            $table->text('technical_report')->nullable();
            $table->text('forensic_report')->nullable();
            $table->string('technical_report_attachment')->nullable();
            $table->string('forensic_report_attachment')->nullable();

            // Star/Flag for unserved notices
            $table->boolean('has_unserved_notice')->default(false);

            // Slip generated flag
            $table->boolean('slip_generated')->default(false);
            $table->datetime('slip_generated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'witness_name', 'witness_cnic', 'witness_nationality', 'witness_passport',
                'witness_address', 'witness_attachment',
                'notice_number', 'notice_type', 'notice_receiver_name', 'notice_date',
                'notice_via', 'notice_person_type', 'notice_description', 'notice_address',
                'notice_phone', 'notice_served', 'notice_served_at', 'notice_count',
                'technical_report', 'forensic_report', 'technical_report_attachment',
                'forensic_report_attachment', 'has_unserved_notice',
                'slip_generated', 'slip_generated_at'
            ]);
        });
    }
};
