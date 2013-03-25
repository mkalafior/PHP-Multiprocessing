<?php
/**
 * Interface for task
 * @author: Sebastian Widelak
 * @class: ITask
 *
 */

interface ITask {
    /**
     * @return mixed
     */
    public function start();
    public function getId();
}