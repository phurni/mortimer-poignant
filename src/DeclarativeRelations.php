<?php namespace Mortimer\Poignant;

class DeclarativeRelationsTypes {
    // Traits can't define constants, so define them here
    const HAS_ONE         = 'hasOne';
    const HAS_MANY        = 'hasMany';
    const BELONGS_TO      = 'belongsTo';
    const BELONGS_TO_MANY = 'belongsToMany';
    const MORPH_TO        = 'morphTo';
    const MORPH_ONE       = 'morphOne';
    const MORPH_MANY      = 'morphMany';
    const MORPH_TO_MANY   = 'morphToMany';
}

trait DeclarativeRelations {

    /**
     * Can be used to ease declaration of relationships in Eloquent models.
     * Follows closely the behavior of the relation methods used by Eloquent, but packing them into an indexed array
     * with relation constants make the code less cluttered.
     *
     * It should be declared with camel-cased keys as the relation name, and value being a mixed array with the
     * relation constant being the first (0) value, the second (1) being the classname and the next ones (optionals)
     * having named keys indicating the other arguments of the original methods: 'foreignKey' (belongsTo, hasOne,
     * belongsToMany and hasMany); 'table' and 'otherKey' (belongsToMany only); 'name', 'type' and 'id' (specific for
     * morphTo, morphOne and morphMany).
     * Exceptionally, the relation type MORPH_TO does not include a classname, following the method declaration of
     * {@link \Illuminate\Database\Eloquent\Model::morphTo}.
     *
     * Example:
     * <code>
     * class Order extends Eloquent {
     *     use DeclarativeRelations;
     *     protected static $relationsData = [
     *         'items'    => [self::HAS_MANY, 'Item'],
     *         'owner'    => [self::HAS_ONE, 'User', 'foreignKey' => 'user_id'],
     *         'pictures' => [self::MORPH_MANY, 'Picture', 'name' => 'imageable']
     *     ];
     * }
     * </code>
     *
     * @see \Illuminate\Database\Eloquent\Model::hasOne
     * @see \Illuminate\Database\Eloquent\Model::hasMany
     * @see \Illuminate\Database\Eloquent\Model::belongsTo
     * @see \Illuminate\Database\Eloquent\Model::belongsToMany
     * @see \Illuminate\Database\Eloquent\Model::morphTo
     * @see \Illuminate\Database\Eloquent\Model::morphOne
     * @see \Illuminate\Database\Eloquent\Model::morphMany
     *
     * @var array
     */
    protected static $relationsData = [];

    /**
     * Associative array containing options the will be merged with every
     * declared relation in the {@link $relationsData} array.
     * This permits to set general default options easily.
     *
     * @var array
     */
    protected static $relationsDefaults = [];

    /**
     * Array of relations used to verify arguments used in the {@link $relationsData}
     *
     * @var array
     */
    protected static $relationTypes = [
        DeclarativeRelationsTypes::HAS_ONE,    DeclarativeRelationsTypes::HAS_MANY,
        DeclarativeRelationsTypes::BELONGS_TO, DeclarativeRelationsTypes::BELONGS_TO_MANY,
        DeclarativeRelationsTypes::MORPH_TO,   DeclarativeRelationsTypes::MORPH_ONE, DeclarativeRelationsTypes::MORPH_MANY
    ];

    /**
     * Returns the names of the relations declared in the array {@link $relationsData}.
     * This method, {@link getDeclaredRelationOptions} and {@link hasDeclaredRelation}
     * are the only one to override if you want to fetch the relation data from another source.
     *
     * @return array
     */
    protected static function getDeclaredRelations()
    {
        return array_keys(static::$relationsData);
    }

    /**
     * Return the declarative relation options array, picked from {@link $relationsData}.
     * This method, {@link hasDeclaredRelation} and {@link getDeclaredRelations}
     * are the only one to override if you want to fetch the relation data from another source.
     *
     * @param string $relationName the relation key, camel-case version
     * @return array
     */
    protected static function getDeclaredRelationOptions($relationName)
    {
        return array_merge(static::$relationsDefaults, static::$relationsData[$relationName]);
    }

    /**
     * Checks for the existance of a relation in the declarative array {@link $relationsData}.
     * This method, {@link getDeclaredRelationOptions} and  {@link getDeclaredRelations}
     * are the only one to override if you want to fetch the relation data from another source.
     *
     * @param string $relationName the relation key, camel-case version
     * @return bool
     */
    protected static function hasDeclaredRelation($relationName)
    {
        return array_key_exists($relationName, static::$relationsData);
    }

    /**
     * Looks for the relation in the {@link $relationsData} array and does the correct magic as Eloquent would require
     * inside relation methods. For more information, read the documentation of the mentioned property.
     *
     * @param string $relationName the relation key, camel-case version
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     * @throws \InvalidArgumentException when the first param of the relation is not a relation type constant,
     *      or there's one or more arguments missing
     * @see DeclarativeRelations::relationsData
     */
    protected function handleRelationalArray($relationName)
    {
        $relation     = static::getDeclaredRelationOptions($relationName);
        $relationType = $relation[0];
        $errorHeader  = "Relation '$relationName' on model '".get_called_class();

        if (!in_array($relationType, static::$relationTypes)) {
            throw new \InvalidArgumentException($errorHeader.
            ' should have as first param one of the relation constants of the DeclarativeRelations trait.');
        }
        if (!isset($relation[1]) && $relationType != DeclarativeRelationsTypes::MORPH_TO) {
            throw new \InvalidArgumentException($errorHeader.
            ' should have at least two params: relation type and classname.');
        }
        if (isset($relation[1]) && $relationType == DeclarativeRelationsTypes::MORPH_TO) {
            throw new \InvalidArgumentException($errorHeader.
            ' is a morphTo relation and should not contain additional arguments.');
        }

        $verifyArgs = function (array $opt, array $req = array()) use ($relationName, &$relation, $errorHeader) {
            $missing = array('req' => array(), 'opt' => array());

            foreach (array('req', 'opt') as $keyType) {
                foreach ($$keyType as $key) {
                    if (!array_key_exists($key, $relation)) {
                        $missing[$keyType][] = $key;
                    }
                }
            }

            if ($missing['req']) {
                throw new \InvalidArgumentException($errorHeader.
                    ' should contain the following key(s): '.join(', ', $missing['req']));
            }
            if ($missing['opt']) {
                foreach ($missing['opt'] as $include) {
                    $relation[$include] = null;
                }
            }
        };

        switch ($relationType) {
            case DeclarativeRelationsTypes::HAS_ONE:
            case DeclarativeRelationsTypes::HAS_MANY:
            case DeclarativeRelationsTypes::BELONGS_TO:
                $verifyArgs(array('foreignKey'));
                return $this->$relationType($relation[1], $relation['foreignKey']);

            case DeclarativeRelationsTypes::BELONGS_TO_MANY:
                $verifyArgs(array('table', 'foreignKey', 'otherKey'));
                $relationship = $this->$relationType($relation[1], $relation['table'], $relation['foreignKey'], $relation['otherKey']);
                if(isset($relation['pivotKeys']) && is_array($relation['pivotKeys']))
                    $relationship->withPivot($relation['pivotKeys']);
                if(isset($relation['timestamps']) && $relation['timestamps']==true)
                    $relationship->withTimestamps();
                return $relationship;

            case DeclarativeRelationsTypes::MORPH_TO:
                $verifyArgs(array('name', 'type', 'id'));
                return $this->$relationType($relation['name'], $relation['type'], $relation['id']);

            case DeclarativeRelationsTypes::MORPH_ONE:
            case DeclarativeRelationsTypes::MORPH_MANY:
                $verifyArgs(array('type', 'id'), array('name'));
                return $this->$relationType($relation[1], $relation['name'], $relation['type'], $relation['id']);
        }
    }

    /**
     * Handle dynamic method calls into the method.
     * Overrided from {@link Eloquent} to implement recognition of the {@link $relationsData} array.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (static::hasDeclaredRelation($method)) {
            return $this->handleRelationalArray($method);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     * Overriden from {@link Eloquent\Model} to allow the usage of the intermediary methods to handle the
     *  {@link $relationsData} array.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $otherKey
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation))
        {
            $backtrace = debug_backtrace(false);
            $caller = ($backtrace[1]['function'] == 'handleRelationalArray')? $backtrace[3] : $backtrace[1];

            $relation = $caller['function'];
        }
        
        return parent::belongsTo($related, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define an polymorphic, inverse one-to-one or many relationship.
     * Overriden from {@link Eloquent\Model} to allow the usage of the intermediary methods to handle the
     *  {@link $relationsData} array.
     *
     * @param  string  $name
     * @param  string  $type
     * @param  string  $id
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function morphTo($name = null, $type = null, $id = null)
    {
        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        if (is_null($name))
        {
            $backtrace = debug_backtrace(false);
            $caller = ($backtrace[1]['function'] == 'handleRelationalArray')? $backtrace[3] : $backtrace[1];

            $name = snake_case($caller['function']);
        }
        
        return parent::morphTo($name, $type, $id);
    }

    /**
     * Get an attribute from the model.
     * Overrided from {@link Eloquent} to implement recognition of the {@link $relationsData} array.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $attr = parent::getAttribute($key);

        if ($attr === null) {
            $camelKey = camel_case($key);
            if (static::hasDeclaredRelation($camelKey)) {
                return $this->getRelationshipFromMethod($key, $camelKey);
            }
        }

        return $attr;
    }

}
