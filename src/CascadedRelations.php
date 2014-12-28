<?php namespace Mortimer\Poignant;

trait CascadedRelations {

    /**
     * Configuration array to annouce relations that must be handled in cascade.
     *
     * Associative array whose key is the relation name and the value is an array of key-value pairs of these options:
     *    'dependentSave' => true    // Will save the relation content when the record's save() method is called.
     *    'dependentDelete' => true  // Will delete the related records content when the record's delete() method is called.
     *
     * Example:
     * <code>
     * class Order extends Eloquent {
     *     use CascadedRelations;
     *     protected static $cascadedRelations = [
     *         'items'    => ['dependentSave' => true],
     *         'pictures' => ['dependentSave' => true, 'dependentDelete' => true]
     *     ];
     * }
     * </code>
     *
     * @var array
     */
    protected static $cascadedRelations = [];

    /**
     * Hold buffer for relations that gets filled by the <relationName>_ids attribute
     *
     * @var array
     */
    protected $cascadedIdsAttributesContent = [];
    
    /**
     * Returns the names of the relations declared either by the {@link DelclarativeRelations} trait
     * or in the {@link $cascadedRelations} array.
     *
     * @return array
     */
    protected static function getCascadedRelations()
    {
        if (method_exists(get_called_class(), 'getDeclaredRelations'))
            return forward_static_call([get_called_class(), 'getDeclaredRelations']);
        else
            return array_keys(static::$cascadedRelations);
    }
    
    /**
     * Returns the options for the passed relation name. Will pick either from the relations declared by the
     * {@link DelclarativeRelations} trait or in the {@link $cascadedRelations} array.
     *
     * @return assoc
     */
    protected static function getCascadedRelationOptions($relationName)
    {
        if (method_exists(get_called_class(), 'getDeclaredRelationOptions'))
            return forward_static_call([get_called_class(), 'getDeclaredRelationOptions'], $relationName);
        else
            return static::$cascadedRelations[$relationName];
    }
    
    /**
     * Returns the names of the relations that match the passed options.
     *
     * @param assoc $for An array containing the options that must match. Keys and values must match,
     *                   except when passing null as value which means catch-any value of option.
     *
     * @return array
     */
    protected static function getCascadedRelationsFor($for = [])
    {
        $relations = static::getCascadedRelations();
        
        // Now filter the $relations to keep only those having option 'dependentSave'.
        // WARNING: We can't use array_filter() or any method that takes a Closure because we will
        // loose the real called_class when calling getCascadedRelationOptions and get instead the 
        // class where the trait has been defined (as of PHP 5.5).
        // This will crash due to the static property $relationsData being defined empty on it.
        $filtered = [];
        foreach ($relations as $relation) {
          // Extract the desired options from the relation
          $options = array_only(static::getCascadedRelationOptions($relation), array_keys($for));
          
          // Now check if we have at least one match on the key/values pair, note that a value of null
          // is a catch-all for the corresponding key
          $diff = array_uintersect_assoc($options, $for, function($a,$b) {
              if ($a === null || $b === null) return 0;
              return strcmp($a,$b);
          });
          if (!empty($diff)) $filtered[] = $relation;
        }
        
        return $filtered;
    }

    /**
     * Override Model save() function to save related records in a DB transaction.
     * It is mandatory to override it and can't simply add a new method like saveWithRelated() or
     * by overriding push(), because save() is itself called from within the framework from several places
     * and if we want the 'cascading' effect we have no other easy choice.
     *
     */
    public function save(array $options = array())
    {
        $query = $this->newQueryWithoutScopes();

        if ($this->fireModelEvent('saving') === false) {
            return false;
        }
        
        // Hold attributes
        $this->holdIdsAttributes();
        
        // Begin transaction
        \DB::beginTransaction();
        
        // TODO: Save *To relations from records if any

        // Save record
        if ($this->exists) {
            $saved = $this->performUpdate($query, $options);
        }
        else {
            $saved = $this->performInsert($query, $options);
        }
        
        // Save relations
        if ($saved) {
            $saved = $this->saveDependentRelations();
        }
        
        // Commit or rollback
        if ($saved) {
            // Trigger saved event, and touches records if needed
            $this->finishSave($options);
            
            // Commit now that the records have been touched
            \DB::commit();
            
            // Some housekeeping
            $this->freeIdsAttributes();
        }
        else {
            \DB::rollBack();
        }

        return $saved;
    }

    /**
     * Save all relations marked as dependent.
     *
     */
    public function saveDependentRelations()
    {
        return $this->saveRelations(static::getCascadedRelationsFor(['dependentSave' => null]));
    }
    
    /**
     * Save relations whose names are passed in the argument.
     *
     */
    public function saveRelations($relationNames)
    {
        foreach ($relationNames as $relationName) {
            // Save from ids
            if ($ids = array_get($this->cascadedIdsAttributesContent, $relationName.'_ids')) {
                $this->saveRelationFromIds($relationName, $ids);
            }
            // Save from records
            elseif (isset($this->relations[$relationName])) {
                if (!$this->saveRelationFromRecords($relationName)) {
                    // No need to get further on failure
                    return false;
                }
            }
        }
      
        return true;
    }

    /**
     * Set the passed record ids for the relation to the current record.
     *
     */
    protected function saveRelationFromIds($relationName, $ids)
    {
        $relation = $this->{camel_case($relationName)}();
        if (static::isRelationToMany($relation)) {
            $this->{$relationName}()->sync($ids);
        }
        elseif (static::isRelationMany($relation)) {
            $this->syncRelatedIds($relationName, $ids);
        }
        elseif (static::isRelationOne($relation)) {
            $this->syncRelatedIds($relationName, [$ids]);
        }
    }
    
    /**
     * Sync the passed record ids for the HasOneOrMany relation to the current record.
     *
     */
    protected function syncRelatedIds($relationName, $ids)
    {
        $relation = $this->{camel_case($relationName)}();
        
        // Get currently associated ids
        $currentIds = $relation->getQuery()->lists($relation->getModel()->getKeyName());
        
        // Attach records
        $this->attachRelatedIds($relationName, array_diff($ids, $currentIds));
        
        // Detach records the way it is required
        $this->detachRelatedIds($relationName, array_diff($currentIds, $ids));
    }
    
    /**
     * Attach the passed record ids for the HasOneOrMany relation to the current record.
     *
     */
    protected function attachRelatedIds($relationName, $ids)
    {
        $relation = $this->{camel_case($relationName)}();
        $relation->getRelated()->getQuery()->whereIn($relation->getRelated()->getKeyName(), $ids)->update([$relation->getForeignKey() => $this->getKey()]);
    }
    
    /**
     * Detach the passed record ids for the HasOneOrMany relation from the current record.
     * Honors the configured dependentDelete option.
     *
     */
    protected function detachRelatedIds($relationName, $ids)
    {
        $relation = $this->{camel_case($relationName)}();
        $procedure = array_get(static::getCascadedRelationOptions($relationName), 'dependentDelete', true);
        if ($procedure === 'nullify') {
            $relation->getRelated()->getQuery()->whereIn($relation->getRelated()->getKeyName(), $ids)->update([$relation->getForeignKey() => null]);
        }
        elseif ($procedure === 'delete') {
            foreach ($relation->getRelated()->whereIn($relation->getRelated()->getKeyName(), $ids)->get() as $related) {
                $related->delete();
            }
        }
        else {
            $relation->getRelated()->getQuery()->whereIn($relation->getRelated()->getKeyName(), $ids)->delete();
        }
    }

    /**
     * Save the tied records for the relation to the current record.
     *
     */
    protected function saveRelationFromRecords($relationName)
    {
        $relation = $this->{camel_case($relationName)}();
        if (static::isRelationMany($relation) || static::isRelationToMany($relation)) {
            $success = true;
            $this->{$relationName}->each(function ($item) use (&$success, $relation) {
                if (!($relation->save($item))) $success = false;
            });
            return $success;
        }
        elseif (static::isRelationOne($relation)) {
            return $relation->save($this->{$relationName});
        }
    }
    
    /**
     * Move *_ids attributes to a hold buffer so that they are no more part of standard
     * attributes which would have been saved.
     *
     */
    protected function holdIdsAttributes()
    {
        // Save the attribute content before purging
        foreach (static::getCascadedRelationsFor(['dependentSave' => null]) as $relationName) {
            $idsAttributeName = $relationName.'_ids';
            if (array_key_exists($idsAttributeName, $this->attributes)) {
                $this->cascadedIdsAttributesContent[$idsAttributeName] = $this->getAttributeValue($idsAttributeName);
                unset($this->attributes[$idsAttributeName]);
            }
        }
    }
    
    /**
     * Free the held *_ids attributes after the record has been saved
     *
     */
    protected function freeIdsAttributes()
    {
        // Remove held ids attributes content
        foreach (static::getCascadedRelationsFor(['dependentSave' => null]) as $relationName) {
            unset($this->cascadedIdsAttributesContent[$relationName.'_ids']);
        }
    }
    
    /**
     * Helper methods to determine a relationship type
     *
     */
    public static function isRelationTo($relation)
    {
        return $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo || $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphTo;
    }

    public static function isRelationMany($relation)
    {
        return $relation instanceof \Illuminate\Database\Eloquent\Relations\HasMany || $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphMany;
    }

    public static function isRelationOne($relation)
    {
        return $relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne || $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphOne;
    }
    
    public static function isRelationToMany($relation)
    {
        return $relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany || $relation instanceof \Illuminate\Database\Eloquent\Relations\MorphToMany;
    }
}
