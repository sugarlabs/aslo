<?php

class StatisticsHelper extends Helper {

    var $helpers = array('Html');

    /**
     * Formats $array[$member] using number_format.
     * If the member isn't set, outputs a 0.
     */
    function number_format($array, $member) {
        if (empty($array[$member])) {
            return 0;
        } else {
            return $this->Html->number_format($array[$member]);
        }
    }

    function colored_percentage($array, $member) {
        if (empty($array[$member])) {
            return 0;
        } else {
            $num = $array[$member];
            if ($num > 0)
                $color = 'green';
            else if ($num < 0)
                $color = 'red';
            else
                $color = 'blue';
            return "<span style='color: {$color}'>".sprintf('%+.2f%%', $num).'</span>';
        }
    }
}
?>
