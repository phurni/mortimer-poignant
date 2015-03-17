<?php namespace Mortimer\Poignant;

trait ExistingAttributesPersistence {

    /**
     * Boot the trait for a model.
     *
     * @return void
     */
    public static function bootExistingAttributesPersistence()
    {
        // Register once for all, the existing attributes of the table
        $dummy = new static;
        $existingAttributesPersistence_tableColumns = \DB::connection()->getSchemaBuilder()->getColumnListing($dummy->getTable());

        // Listen to the 'saving' event but with a low priority so that any validation is performed before purging the attributes
        $name = get_called_class();
        static::getEventDispatcher()->listen("eloquent.saving: {$name}", function($model) use ($existingAttributesPersistence_tableColumns)
        {
            $model->setRawAttributes(array_only($model->getAttributes(), $existingAttributesPersistence_tableColumns));
        }, -100);
    }
}
