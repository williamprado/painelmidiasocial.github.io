<?php

namespace App\Modules\Bosslike\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class TaskSaveRequest
 * @package Modules\Bosslike\Requests
 */
class SocialUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return[
            'social_id' => 'required|exists:socials,id|unique:social_users,social_id,user_id',

        ];

    }
}
