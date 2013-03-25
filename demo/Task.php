<?php
include_once 'src/ITask.php';
/**
 * Class Task
 * demo sample
 */
class Task implements ITask{
    public $callback;
    public $id;

    public function __construct($id, $callback){
        $this->callback = $callback;
        $this->id = $id;
    }
    /**
     * @return mixed
     */
    public function start()
    {
        return call_user_func($this->callback);
    }

    public function getId() {
        return $this->id;
    }

}