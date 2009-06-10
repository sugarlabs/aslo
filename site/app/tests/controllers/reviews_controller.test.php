<?php

class ReviewsTest extends UnitTestCase {
 
    function testLoad() {
        $this->helper =& new UnitTestHelper();
        $this->controller =& $this->helper->getController('Reviews', $this);
        $this->helper->mockComponents($this->controller, $this);
    }

    function testAddReview() {
        $this->controller->params['controller'] = 'Reviews';
        // set up components needed by controller
        $this->controller->set('paging', array());
        
        $review = array(
            'id'     => '',
            'rating' => 5,
            'title'  => 'Review Test',
            'body'   => 'A long, loooong review.'
        );
        $this->controller->data['Review'] = $review;

        // try it logged out
        $this->helper->callControllerAction($this->controller, 'add', $this, array(7));
        $id = $this->controller->Review->getLastInsertId();
        $this->assertTrue(($id == 0), 'Not adding reviews if not logged in');
        @$this->controller->Review->del($id);
        
        // now logged in
        $this->helper->login($this->controller);
        $this->controller->sandboxAccess = true;
        $this->helper->callControllerAction($this->controller, 'add', $this, array(7));
        $id = $this->controller->Review->getLastInsertId();
        $this->assertTrue(($id > 0), 'Adding a review as a logged in user');

        // try editing this review
        /*
        $review2 = $review;
        $review2['rating'] = 10;
        $this->helper->callControllerAction($this->controller, 'add', $this, array(7));
        $saved = $this->controller->Review->findById($id);
        $this->assertEquals($saved['Review']['rating'], 10, 'Editing a review');
        */
        @$this->controller->Review->del($id);
    }
}

?>
