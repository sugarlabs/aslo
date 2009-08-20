<?php

class CollectionsTest extends WebTestHelper {

    function testLoad() {
        $this->helper =& new UnitTestHelper();
        $this->controller = $this->helper->getController('Collections', $this);
        $this->helper->mockComponents($this->controller, $this);
        $this->Collection = $this->controller->Collection;
        $this->Collection->caching = $this->Collection->cacheQueries = False;

        $this->collection_id = 2;
        $this->user_id = 5;
        $this->nickname = 'cannoli';
        $this->location = $this->actionURI('/collection/'.$this->nickname);
        $this->postUrl = "/collections/vote/{$this->nickname}/";

        $this->Collection->execute("DELETE FROM collections_votes");
    }

    function setup() {
        $this->setMaximumRedirects(0);
    }

    function tearDown() {
        $this->logout();
    }

    function assertVoteCount($collection, $up, $down) {
        $this->Collection->unbindFully();
        $c = $this->Collection->findById($collection);
        $this->assertEqual($up, $c['Collection']['upvotes'],
                           "Should have $up upvotes");
        $this->assertEqual($down, $c['Collection']['downvotes'],
                           "Should have $down downvotes");
    }

    function assertVote($collection, $user, $vote) {
        $r = $this->Collection->execute(
            "SELECT * FROM collections_votes
             WHERE collection_id={$collection} AND user_id={$user}");

        if (is_null($vote)) {
            $this->assertTrue(empty($r));
        } else {
            $this->assertEqual($r[0]['collections_votes']['vote'], $vote,
                               "user $user, collection $collection, vote $vote");
        }
    }

    function testVotes() {
        $this->login();

        $this->assertVoteCount($this->collection_id, 0, 0);

        $this->postAction($this->postUrl . 'up');
        $this->assertRedirect($this->location, 302);
        $this->assertVoteCount($this->collection_id, 1, 0);
        $this->assertVote($this->collection_id, $this->user_id, 1);

        $this->postAction($this->postUrl . 'down');
        $this->assertRedirect($this->location, 302);
        $this->assertVoteCount($this->collection_id, 0, 1);
        $this->assertVote($this->collection_id, $this->user_id, -1);

        $this->postAction($this->postUrl . 'cancel');
        $this->assertRedirect($this->location, 302);
        $this->assertVoteCount($this->collection_id, 0, 0);
        $this->assertVote($this->collection_id, $this->user_id, null);
    }

    function testVoteFailures() {
        // Not logged in.
        $this->assertVoteCount($this->collection_id, 0, 0);
        $this->postAction($this->postUrl . 'up');
        $this->assertResponse(400);
        $this->assertVoteCount($this->collection_id, 0, 0);
        $this->assertVote($this->collection_id, $this->user_id, null);

        // Nothing should change on GET.
        $this->login();
        $this->getAction($this->postUrl . 'up');
        $this->assertRedirect($this->location, 302);
        $this->assertVoteCount($this->collection_id, 0, 0);
        $this->assertVote($this->collection_id, $this->user_id, null);
    }

}
