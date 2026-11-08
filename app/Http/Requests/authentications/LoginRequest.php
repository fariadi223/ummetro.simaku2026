<?php

namespace App\Http\Requests\authentications;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

use App\Traits\ResponseTrait;

class LoginRequest extends FormRequest
{
    use ResponseTrait;
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'username'    => 'required',
            'password' => 'required|min:3',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }


    public function getCredentials()
    {
        // The form field for providing username or password
        // have name of "username", however, in order to support
        // logging users in with both (username and email)
        // we have to check if user has entered one or another
        $username = $this->get('username');

        if ($this->isEmail($username)) {
            return [
                'email' => $username,
                'password' => $this->get('password')
            ];
        }

        return $this->only('username', 'password');
    }

    private function isEmail($param)
    {
        $factory = $this->container->make(ValidationFactory::class);

        return ! $factory->make(
            ['username' => $param],
            ['username' => 'email']
        )->fails();
    }

    /**
     * Custom validation message
     *
     * @return array
     */
    public function messages(): array
    {
        echo "";
        
        return [
            'username.required'    => 'Please give your email',
            'password.required' => 'Please give your password',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors     = (new ValidationException($validator))->errors();
        $errorsMsg  = '';
        
        foreach ($errors as $field => $error) :
            $errorsMsg .= implode(' ', $error) .". \n";
        endforeach;
    
        throw new HttpResponseException(
            $this->responseError(null, $errorsMsg)
        );
    }
}