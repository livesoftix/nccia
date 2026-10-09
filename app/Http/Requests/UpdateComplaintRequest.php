<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $complaint = $this->route('complaint');

        return $complaint
            && ($this->user()?->can('update', $complaint) ?? false);
    }

    public function rules(): array
    {
        return [
            'complainant_name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'cnic' => 'required|string|max:15',
            'contact_no'              => 'required|string|max:20',
            'whatsapp_no'             => 'nullable|string|max:20',
            'gender'                  => 'nullable|string|max:20',
            'email'                   => 'nullable|email:filter|max:255',
            'contact_country_code'    => 'nullable|string|max:8',
            'nationality'             => 'nullable|string|max:50',
            'passport_no'             => 'nullable|string|max:50|required_if:nationality,Dual Nationality Holder|required_if:nationality,Foreigner',
            'address' => 'required|string',
            'post_address' => 'nullable|string',
            'district' => 'nullable|string|max:255',
            'profession' => 'nullable|string|max:255',

            'report_date'        => 'required|date',
            'reporting_time'     => 'nullable|date',
            'diary_no'           => 'nullable|string|max:255',
            'received_via'       => 'required|string|max:255',
            'received_from'      => 'required|string|max:255',
            'cmu'                => 'nullable|string|max:255',
            'priority_type'      => 'nullable|string|max:50',

            'offence_type'       => 'required|string|max:255',
            'crime_mediums'      => 'nullable|array',
            'crime_mediums.*'    => 'string|max:100',
            'amount_involved'    => 'nullable|numeric|min:0',
            'bank_name_sender'   => 'nullable|string|max:255',
            'bank_name_receiver' => 'nullable|string|max:255',
            'account_no_sender'  => 'nullable|string|max:100',
            'account_no_receiver'=> 'nullable|string|max:100',
            'transaction_date'   => 'nullable|date',
            'occurrence_date'    => 'required|date',
            'laws'               => 'nullable|array',
            'laws.*'             => 'string',
            'description'        => 'required|string|max:2000',
            'platforms'          => 'nullable|array',
            'platforms.*'        => 'string|max:100',
            'platform_profile_page'    => 'nullable|string|max:500',
            'platform_username'        => 'nullable|string|max:255',
            'platform_email_involved'  => 'nullable|string|max:255',
            'platform_mobile_involved' => 'nullable|string|max:30',
            'evidence'           => 'nullable|array',
            'evidence.*'         => 'string',
            'initial_accused'    => 'nullable',

            'operator_name'         => 'nullable|string|max:255',
            'operator_designation'  => 'nullable|string|max:255',
            'entry_time'            => 'nullable|date',
            'operator_remarks'      => 'nullable|string',

            'source'     => 'nullable|string|max:50',
            'circle_id'  => 'nullable|integer|exists:circles,id',
            'operator_id' => 'nullable|integer|exists:users,id',
            'scrutiny_result' => 'nullable|string|in:complete,incomplete,invalid,irrelevant',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
            'cnic_front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'cnic_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'passport_attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'picture' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',

            // Per-accused identity files (previously UNVALIDATED → RCE vector). Lock to images/pdf.
            'accused_cnic_front'   => 'nullable|array',
            'accused_cnic_front.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'accused_cnic_back'    => 'nullable|array',
            'accused_cnic_back.*'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'accused_picture'      => 'nullable|array',
            'accused_picture.*'    => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
            'accused_passport'     => 'nullable|array',
            'accused_passport.*'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',

            // Assign VO on Complete Registration (same form)
            'verification_officer_id' => 'nullable|integer|exists:users,id',
            'assign_priority_type'    => 'nullable|string|in:normal,high,critical',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->email)) {
            $email = trim(str_replace(',', '.', $this->email));
            if ($email === '' || preg_match('/^(na|n\/a|nil|none|-)$/i', $email)) {
                $this->merge(['email' => null]);
            } else {
                $this->merge(['email' => $email]);
            }
        }

        foreach (['platform_email_involved', 'platform_username', 'platform_profile_page', 'platform_mobile_involved'] as $field) {
            if ($this->has($field) && is_string($this->$field) && preg_match('/^(na|n\/a|nil|none|-)$/i', trim($this->$field))) {
                $this->merge([$field => null]);
            }
        }

        if ($this->has('initial_accused') && is_string($this->initial_accused)) {
            $decoded = json_decode($this->initial_accused, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['initial_accused' => $decoded]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'email.email' => 'Email invalid hai — example: name@gmail.com (comma nahi, dot use karein).',
        ];
    }
}
