<?php namespace Mortimer\Poignant;

class ModelValidator extends \Illuminate\Validation\Validator {
    // The model under validation
    protected $model = null;
  
    /**
     * Sets the model under validation.
     */
    public function setModel($model) {
        $this->model = $model;
    }

    /**
     * Rewrite replacements to try first replace* methods in the model
     *
     */
    protected function doReplacements($message, $attribute, $rule, $parameters)
    {
        if (method_exists($this->model, $replacer = "replace{$rule}")) {
            $message = str_replace(':attribute', $this->getAttribute($attribute), $message);
            return call_user_func([$this->model, $replacer], $message, $attribute, $rule, $parameters, $this);
        }
        return parent::doReplacements($message, $attribute, $rule, $parameters);
    }
  
    /**
     * Hook into Validator method call, to redirect validate* methods to the model
     *
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->model, $method)) {
            $parameters[] = $this;
            return call_user_func_array([$this->model, $method], $parameters);
        }
        return parent::__call($method, $parameters);
    }
}
