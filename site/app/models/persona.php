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

        $this->bindOnly($bindings);
        $persona = $this->findById($id);

        $p =& $persona['Persona'];
        $p['prefix'] = $prefix = $this->urlPrefix($p['persona_id']);

        $p['thumb'] = join('/', array(PERSONAS_IMAGE_ROOT_SSL, $prefix, 'preview.jpg'));

        $addon = $this->Addon->getAddon($p['addon_id'], array('all_categories', 'default_fields'));
        $persona['Addon'] = array_merge($addon, $addon['Addon']);
        unset($persona['Addon']['Addon']);
        $p['json'] = json_encode($this->jsonData($persona));

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

    /* Get an array of all the pieces that go into the Personas JSON element. */
    function jsonData($persona) {
        $p = $persona['Persona'];
        $prefix = $this->urlPrefix($p['persona_id']);
        return array(
            'id' => $p['id'],
            'name' => $persona['Translation']['name']['string'],
            'accentcolor' => hex_color($p['accentcolor']),
            'textcolor' => hex_color($p['textcolor']),
            'category' => $persona['Addon']['Category'][0]['Translation']['name']['string'],
            'author' => nullable($p['author']),
            'description' => nullable($persona['Translation']['description']['string']),
            'header' => join('/', array(PERSONAS_IMAGE_ROOT, $prefix, $p['header'])),
            'footer' => join('/', array(PERSONAS_IMAGE_ROOT, $prefix, $p['footer'])),
            'headerURL' => join('/', array(PERSONAS_IMAGE_ROOT, $prefix, $p['header'])),
            'footerURL' => join('/', array(PERSONAS_IMAGE_ROOT, $prefix, $p['footer'])),
            'previewURL' => join('/', array(PERSONAS_IMAGE_ROOT, $prefix, 'preview.jpg')),
            'iconURL' => join('/', array(PERSONAS_IMAGE_ROOT, $prefix, 'preview_small.jpg')),
        );
    }
}

function nullable($thing) {
    return $thing ? $thing : null;
}

function hex_color($color) {
    return $color ? '#'.$color : null;
}
