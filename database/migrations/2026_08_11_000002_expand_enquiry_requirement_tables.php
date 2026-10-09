<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (!Schema::hasColumn('enquiries', 'cfr_type')) {
                $table->string('cfr_type', 20)->nullable()->after('cfr_summary'); // CFR / SCFR
            }
            if (!Schema::hasColumn('enquiries', 'cfr_date')) {
                $table->date('cfr_date')->nullable()->after('cfr_type');
            }
            if (!Schema::hasColumn('enquiries', 'charge_against')) {
                $table->text('charge_against')->nullable()->after('cfr_date');
            }
            if (!Schema::hasColumn('enquiries', 'oral_evidence')) {
                $table->text('oral_evidence')->nullable()->after('charge_against');
            }
            if (!Schema::hasColumn('enquiries', 'documentary_evidence')) {
                $table->text('documentary_evidence')->nullable()->after('oral_evidence');
            }
            if (!Schema::hasColumn('enquiries', 'plea')) {
                $table->text('plea')->nullable()->after('documentary_evidence');
            }
            if (!Schema::hasColumn('enquiries', 'conclusion')) {
                $table->text('conclusion')->nullable()->after('plea');
            }
            if (!Schema::hasColumn('enquiries', 'cfr_remarks')) {
                $table->text('cfr_remarks')->nullable()->after('conclusion');
            }
        });

        Schema::table('enquiry_witnesses', function (Blueprint $table) {
            $cols = [
                'father_name' => fn (Blueprint $t) => $t->string('father_name')->nullable()->after('name'),
                'relation' => fn (Blueprint $t) => $t->string('relation')->nullable()->after('father_name'),
                'gender' => fn (Blueprint $t) => $t->string('gender', 20)->nullable()->after('relation'),
                'domicile_district' => fn (Blueprint $t) => $t->string('domicile_district')->nullable()->after('cnic'),
                'occupation' => fn (Blueprint $t) => $t->string('occupation')->nullable()->after('domicile_district'),
                'is_government' => fn (Blueprint $t) => $t->boolean('is_government')->default(false)->after('occupation'),
                'department_name' => fn (Blueprint $t) => $t->string('department_name')->nullable()->after('is_government'),
                'designation' => fn (Blueprint $t) => $t->string('designation')->nullable()->after('department_name'),
                'scale' => fn (Blueprint $t) => $t->string('scale')->nullable()->after('designation'),
                'contact_no' => fn (Blueprint $t) => $t->string('contact_no', 20)->nullable()->after('scale'),
                'whatsapp_no' => fn (Blueprint $t) => $t->string('whatsapp_no', 20)->nullable()->after('contact_no'),
                'mailing_address' => fn (Blueprint $t) => $t->text('mailing_address')->nullable()->after('whatsapp_no'),
                'permanent_address' => fn (Blueprint $t) => $t->text('permanent_address')->nullable()->after('mailing_address'),
                'picture' => fn (Blueprint $t) => $t->string('picture')->nullable()->after('attachment'),
                'statement_attachment' => fn (Blueprint $t) => $t->string('statement_attachment')->nullable()->after('picture'),
            ];
            foreach ($cols as $name => $add) {
                if (!Schema::hasColumn('enquiry_witnesses', $name)) {
                    $add($table);
                }
            }
        });

        Schema::table('enquiry_notices', function (Blueprint $table) {
            if (!Schema::hasColumn('enquiry_notices', 'appearance_date')) {
                $table->dateTime('appearance_date')->nullable()->after('appeared_at');
            }
            if (!Schema::hasColumn('enquiry_notices', 'appearance_remarks')) {
                $table->string('appearance_remarks')->nullable()->after('appearance_date'); // appeared / did_not_appear
            }
            if (!Schema::hasColumn('enquiry_notices', 'sequence_no')) {
                $table->unsignedTinyInteger('sequence_no')->nullable()->after('enquiry_id');
            }
        });

        Schema::table('enquiry_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('enquiry_activities', 'diary_no')) {
                $table->string('diary_no')->nullable()->after('type');
            }
        });

        if (!Schema::hasTable('enquiry_accused')) {
            Schema::create('enquiry_accused', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('cnic')->nullable();
                $table->string('father_name')->nullable();
                $table->string('gender', 20)->nullable();
                $table->string('contact_no', 20)->nullable();
                $table->string('whatsapp_no', 20)->nullable();
                $table->string('email')->nullable();
                $table->text('postal_address')->nullable();
                $table->text('permanent_address')->nullable();
                $table->string('religion')->nullable();
                $table->string('district_domicile')->nullable();
                $table->string('identification_mark')->nullable();
                $table->string('occupation')->nullable();
                $table->boolean('is_government')->default(false);
                $table->string('department_name')->nullable();
                $table->string('designation')->nullable();
                $table->string('cnic_attachment')->nullable();
                $table->string('passport_attachment')->nullable();
                $table->string('nadra_verisys_attachment')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('enquiry_attachments')) {
            Schema::create('enquiry_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
                $table->string('enquiry_number')->nullable();
                $table->date('attachment_date')->nullable();
                $table->string('title')->nullable();
                $table->string('file_path')->nullable();
                $table->string('original_name')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('enquiry_requisitions')) {
            Schema::create('enquiry_requisitions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
                $table->string('type'); // bank, telecom, nadra, travel
                $table->string('email_to')->nullable();
                $table->string('subject')->nullable();
                $table->text('body')->nullable();
                $table->string('status')->default('draft'); // draft, sent, failed
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('authorized_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_requisitions');
        Schema::dropIfExists('enquiry_attachments');
        Schema::dropIfExists('enquiry_accused');

        Schema::table('enquiry_activities', function (Blueprint $table) {
            if (Schema::hasColumn('enquiry_activities', 'diary_no')) {
                $table->dropColumn('diary_no');
            }
        });

        Schema::table('enquiry_notices', function (Blueprint $table) {
            foreach (['appearance_date', 'appearance_remarks', 'sequence_no'] as $col) {
                if (Schema::hasColumn('enquiry_notices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('enquiry_witnesses', function (Blueprint $table) {
            $cols = [
                'father_name', 'relation', 'gender', 'domicile_district', 'occupation',
                'is_government', 'department_name', 'designation', 'scale', 'contact_no',
                'whatsapp_no', 'mailing_address', 'permanent_address', 'picture', 'statement_attachment',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('enquiry_witnesses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('enquiries', function (Blueprint $table) {
            foreach (['cfr_type', 'cfr_date', 'charge_against', 'oral_evidence', 'documentary_evidence', 'plea', 'conclusion', 'cfr_remarks'] as $col) {
                if (Schema::hasColumn('enquiries', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
