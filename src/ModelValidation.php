<?php namespace Mortimer\Poignant;

trait ModelValidation {
    use \Watson\Validating\ValidatingTrait {
        \Watson\Validating\ValidatingTrait::getValidator as getTraitValidator;
        \Watson\Validating\ValidatingTrait::getValidationAttributeNames as getTraitValidationAttributeNames;
        \Watson\Validating\ValidatingTrait::makeValidator as makeTraitValidator;
    }

    /**
     * Get the Validator instance. Note that the default implementation
     * will get the default Validator factory and inject our ModelValidator
     * by setting a new resolver, so if you already injected a resolver it will
     * be overridden.
     *
     * @return \Illuminate\Validation\Factory
     * @see \Illuminate\Validation\Factory::resolver
     */
    public function getValidator()
    {
        $factory = $this->getTraitValidator();
        $factory->resolver(function($translator, $data, $rules, $messages, $attributes) {
            return new ModelValidator($translator, $data, $rules, $messages, $attributes);
        });
        return $factory;
    }
    
    /**
     * Get the validating attribute names.
     * Merge the custom defined ones in $this->validationAttributeNames
     * with the ones defined in the locale file.
     *
     * @return mixed
     */
    public function getValidationAttributeNames()
    {
        // Grab customized names if they exists
        $names = $this->getTraitValidationAttributeNames();
        if ($names === null) $names = [];

        // Okay, now let's try localized ones
        $localizedNames = $this->getModelTranslation('attributes');
        if ($localizedNames === null) $localizedNames = [];
        
        // Merge them
        $names = array_merge($localizedNames, $names);
        
        // Transform an empty array to null to respect the return behaviour of the parent
        return empty($names) ? null : $names;
    }

    /**
     * Get the custom messages for the validator
     * Merge the custom defined ones in $this->validationCustomMessages
     * with the ones defined in the locale file.
     *
     * @return array
     */
    public function getValidationCustomMessages()
    {
        // Grab customized messages if they exists
        $messages = isset($this->validationCustomMessages) ? $this->validationCustomMessages : [];
      
        // Okay, now let's try localized ones
        $localizedMessages = $this->getModelTranslation('validation');
        if ($localizedMessages === null) $localizedMessages = [];
        
        // Merge them
        $messages = array_merge($localizedMessages, $messages);
        
        // Transform an empty array to null to respect the return behaviour of the parent
        return empty($messages) ? null : $messages;
    }

    /**
     * Set the custom messages for the validator.
     *
     * @param  array  $messages
     * @return void
     */
    public function setValidationCustomMessages(array $messages)
    {
        $this->validationCustomMessages = $messages;
    }

    /**
     * Make a Validator instance for a given ruleset.
     * Override ValidatingTrait method by also injecting customMessages.
     *
     * @param  array $rules
     * @return \Illuminate\Validation\Factory
     */
    protected function makeValidator($rules = [])
    {
        $validator = $this->makeTraitValidator($rules);
        
        // Inject the validating model
        $validator->setModel($this);

        // Inject custom messages
        if ($customMessages = $this->getValidationCustomMessages()) {
            $validator->setCustomMessages($customMessages);
        }

        return $validator;
    }
  
    /**
     * An alternative more semantic shortcut to the message container.
     * (that matches Illuminate\Validation\Validator)
     */
    public function errors()
    {
        return $this->getErrors();
    }
    
    /**
     * Fetches the translation (localization) scoped to the current model.
     * Returns null if no translation available for the passed key.
     *
     * @param  string  $subKey the key to fetch, will be under the model name in the locale file
     * @return mixed
     */
    public function getModelTranslation($subKey)
    {
        $key = $this->getModelLocaleKey().'/'.$subKey;
        if (\Lang::has($key)) {
            return \Lang::get($key);
        }
        else {
            return null;
        }
    }
  
    /**
     * Returns the model name in a string that may be used as a key in the locales translations.
     *
     * @return string
     */
    public function getModelLocaleKey()
    {
        return snake_case(str_plural(get_called_class()));
    }
}
