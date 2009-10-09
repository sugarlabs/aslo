<?php

class Persona extends AppModel {

    var $name = 'Persona';

    var $belongsTo = array(
        'Addon' => array(
            'className' => 'Addon',
            'foreignKey' => 'addon_id',
        ),
    );

    var $translated_fields = array(
        'name',
        'description',
    );

    function getPersona($id, $associations=array()) {
        list($in_cache, $cached) = $this->startCache($id, $associations);
        if ($in_cache)
            return $cached;

        $bindings = array();

        if (in_array('addon', $associations)) {
            $bindings[] = 'Addon';
        }

        $this->bindOnly($bindings);
        $persona = $this->findById($id);

        $p =& $persona['Persona'];
        $p['prefix'] = $prefix = $this->urlPrefix($p['persona_id']);

        $p['thumb'] = join('/', array(PERSONAS_IMAGE_ROOT, $prefix, 'preview.jpg'));

        // XXX: I don't know why cake isn't pulling anything from the Addons
        // model here, so we'll do it manually.  Oh joy.
        $addon = $this->Addon->getAddon($p['addon_id'], array('default_fields'));
        $persona['Addon'] = $addon['Addon'];

        return $this->endCache($persona);
    }

    function getPersonaList($ids, $associations=array()) {
        $a = array();
        foreach ($ids as $id) $a[] = $this->getPersona($id, $associations);
        return $a;
    }

    /** Personas files are stored in directories corresponding to the last two
     * digits of their id.  For example, the persona with persona_id=1234 has
     * a prefix of 3/4/1234.
     */
    function urlPrefix($id) {
        $a = $id % 10;
        $b = ($id / 10) % 10;
        return join('/', array($b, $a, $id));
    }
}
