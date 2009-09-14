<?php

class HowtoVote extends AppModel {

    var $name = 'HowtoVote';

    function getVote($id, $associations=array()) {
        list($in_cache, $cached) = $this->startCache($id, $associations);
        if ($in_cache)
            return $cached;

        $v = $this->execute("SELECT COUNT(vote) AS upvotes
                             FROM howto_votes
                             WHERE howto_id={$id}");
        $vote = array('Vote' => array('id' => $id,
                                      'upvotes' => $v[0][0]['upvotes']));
        return $this->endCache($vote);
    }

    function getVotes($ids) {
        $a = array();
        foreach ($ids as $id) $a[$id] = $this->getVote($id);
        return $a;
    }
}
