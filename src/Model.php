<?php namespace Mortimer\Poignant;

abstract class Model extends \Illuminate\Database\Eloquent\Model {
    use ModelValidation, UserStamping, DeclarativeRelations, CascadedRelations;
    
    const HAS_ONE         = DeclarativeRelationsTypes::HAS_ONE;
    const HAS_MANY        = DeclarativeRelationsTypes::HAS_MANY;
    const BELONGS_TO      = DeclarativeRelationsTypes::BELONGS_TO;
    const BELONGS_TO_MANY = DeclarativeRelationsTypes::BELONGS_TO_MANY;
    const MORPH_TO        = DeclarativeRelationsTypes::MORPH_TO;
    const MORPH_ONE       = DeclarativeRelationsTypes::MORPH_ONE;
    const MORPH_MANY      = DeclarativeRelationsTypes::MORPH_MANY;
    const MORPH_TO_MANY   = DeclarativeRelationsTypes::MORPH_TO_MANY;
}
