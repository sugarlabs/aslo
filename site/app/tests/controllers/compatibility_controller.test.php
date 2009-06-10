<?php


class CompatTest extends UnitTestCase {

    function testLoad() {
        $this->helper = new UnitTestHelper();
        $this->controller = $this->helper->getController('Compatibility', $this);
        $this->helper->mockComponents($this->controller, $this);
    }

    /* Helper to get a totals array. */
    function assert100($other, $alpha, $beta, $latest, $outof) {
        $t = array('adu95' => $outof);
        $t[COMPAT_OTHER]['adu']  = $other;
        $t[COMPAT_ALPHA]['adu']  = $alpha;
        $t[COMPAT_BETA]['adu']   = $beta;
        $t[COMPAT_LATEST]['adu'] = $latest;

        $percentages = $this->controller->_percentages($t);
        $this->assertEqual(100, array_sum($percentages));
    }

    function testPercentages() {
        /* The sum of the percentages should always be 100. */
        $this->assert100(1, 2, 3, 4, 10);  // Even
        $this->assert100(1, 2, 3, 3, 9);   // Over
        $this->assert100(3, 3, 4, 9, 19);  // Under
    }
}
?>
