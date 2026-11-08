<?php

namespace App\Http\Requests;

use App\Support\HomeAreas;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MonitoringPointRequest extends FormRequest
{
    // When editing, ownership is checked before validation, so other residents get a 404 rather than error messages
    public function authorize(): bool|Response
    {
        $point = $this->route('point');

        return $point ? Gate::inspect('update', $point) : true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            // One of the preset rooms, "other" with the resident's own name for the area, or nothing
            'area' => ['nullable', 'string', Rule::in([...HomeAreas::PRESETS, 'other'])],
            'area_other' => ['nullable', 'required_if:area,other', 'string', 'max:40'],
            'surface_description' => ['nullable', 'string', 'max:500'],
            'follow_up_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2099-12-31'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Give this spot a short name, such as “Bedroom wall beside the window.”',
            'label.max' => 'Keep the name under 80 characters.',
            'area.in' => 'Choose one of the areas, or Other to name your own.',
            'area_other.required_if' => 'Name the area, such as “Laundry area” or “Bedroom 2”.',
            'area_other.max' => 'Keep the area name under 40 characters.',
            'surface_description.max' => 'Keep the description under 500 characters.',
            'follow_up_on.*' => 'Choose a follow-up date from the calendar, or leave it empty.',
        ];
    }

    // The point's fields as stored: a custom area replaces "other", and a preset typed by hand files under that preset
    public function pointData(): array
    {
        $data = $this->safe()->only(['label', 'surface_description', 'follow_up_on']);

        if ($this->has('area')) {
            $area = $this->validated('area');
            $data['area'] = HomeAreas::name($area === 'other' ? $this->validated('area_other') : $area);
        }

        return $data;
    }
}
