<?php namespace Mortimer\Poignant;

trait ExistingAttributesPersistence {

  /**
   * Column names for the current model DB table
   *
   * @var array
   */
    protected static $ExistingAttributesPersistence_tableColumns = [];

    /**
     * Boot the trait for a model.
     *
     * @return void
     */
    public static function bootExistingAttributesPersistence()
    {
        // Register once for all, the existing attributes of the table
        $dummy = new static;
        static::$ExistingAttributesPersistence_tableColumns = \DB::connection()->getSchemaBuilder()->getColumnListing($dummy->getTable());
        
        // Listen to the 'saving' event but with a low priority so that any validation is performed before purging the attributes
        $name = get_called_class();
        static::getEventDispatcher()->listen("eloquent.saving: {$name}", function($model)
        {
            // Keep only the attributes that exists in the table
            $model->setRawAttributes(array_only($model->getAttributes(), static::$ExistingAttributesPersistence_tableColumns));
        }, -100);
    }
}
