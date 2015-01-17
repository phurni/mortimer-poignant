<?php namespace Mortimer\Poignant;

trait UserStamping {

    /** customizable constants for user audit **/
    protected static $CREATED_BY = 'created_by_id';
    protected static $UPDATED_BY = 'updated_by_id';
    protected static $DELETED_BY = 'deleted_by_id';
    
    /**
     * Returns the value to put into the stamp attributes.
     * Defaults to \Auth::user()->getKey()
     */
    public function getUserStampValue()
    {
        return \Auth::user()->getKey();
    }

    /**
     * Boot the trait for a model.
     *
     * @return void
     */
    public static function bootUserStamping()
    {
        $dummy = new static;

        // Audit user on creation
        if ($dummy->hasUserStampingTableColumn(static::$CREATED_BY) || $dummy->hasUserStampingTableColumn(static::$UPDATED_BY)) {
            $realCalledClass = get_called_class();  // BUG: the static keyword in closure returns the wrong called class, so pass it explicitely
            static::creating(function($model) use ($realCalledClass)
            {
                if ($model->hasUserStampingTableColumn($realCalledClass::$CREATED_BY)) $model->{$realCalledClass::$CREATED_BY} = $model->getUserStampValue();
                if ($model->hasUserStampingTableColumn($realCalledClass::$UPDATED_BY)) $model->{$realCalledClass::$UPDATED_BY} = $model->getUserStampValue();
            });
        }

        // Audit user when updating
        if ($dummy->hasUserStampingTableColumn(static::$UPDATED_BY)) {
            $realCalledClass = get_called_class();  // BUG: the static keyword in closure returns the wrong called class, so pass it explicitely
            static::updating(function($model) use ($realCalledClass)
            {
                $model->{$realCalledClass::$UPDATED_BY} = $model->getUserStampValue();
            });
        }

        // Also audit user when soft deleting
        if ($dummy->hasUserStampingTableColumn(static::$DELETED_BY) && isset($dummy->forceDeleting)) {
            $realCalledClass = get_called_class();  // BUG: the static keyword in closure returns the wrong called class, so pass it explicitely
            static::deleting(function($model) use ($realCalledClass)
            {
                if (!$model->forceDeleting)
                {
                    $model->{$realCalledClass::$DELETED_BY} = $model->getUserStampValue();
                    $query = $model->newQuery()->where($model->getKeyName(), $model->getKey());
                    $query->update(array($realCalledClass::$DELETED_BY => $model->{$realCalledClass::$DELETED_BY}));
                }
            });

            static::restoring(function($model) use ($realCalledClass)
            {
                $model->{$realCalledClass::$DELETED_BY} = null;
            });
        }
    }

    /**
     * Add DB table columns meta info
     *
     */
    protected function getUserStampingTableColumns() {
        return \DB::connection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }

    /**
     * Add DB table columns meta info
     *
     */
    protected function hasUserStampingTableColumn($name) {
        return in_array($name, $this->getUserStampingTableColumns());
    }

}
