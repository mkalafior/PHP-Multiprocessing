<?php
require_once('IObservable.php');
require_once('Observer.php');

/**
 * @author: Sebastian Widelak
 * @class: Worker
 */
class Worker implements IObservable
{
    const PARENT = 0;
    const CHILD = 1;
    const BIDIRECTIONAL = 2;
    const DIRECTIONAL = 3;
    const COULD_NOT_CREATE_SHARED_MEMORY = 4;

    private $_errors = array(
        self::COULD_NOT_CREATE_SHARED_MEMORY => 'Couldn\'t create shared memory segment'
    );

    private $_id;
    private $_tasks = array();
    private $_dir = self::DIRECTIONAL;
    private $_shmId;
    /**
     * @var Observer
     */
    protected $_observer;

    /**
     * @param integer $id
     */
    public function  __construct($id)
    {
        $this->_id = $id;
        $this->setObserver(new Observer());
        /**
         * What it should do on start
         */
        $this->addListener('start', array($this, 'startTask'));
    }

    /**
     * @param Observer $observer
     * @return $this
     */
    public function setObserver(Observer $observer)
    {
        $this->_observer = $observer;
        return $this;
    }

    /**
     * @return Observer
     */
    public function getObserver()
    {
        return $this->_observer;
    }


    /**
     * @param $eventType
     * @param $args
     * @return $this
     */
    public function fireEvent($eventType, $args)
    {
        $this->getObserver()->fireEvent($eventType, $args);
        return $this;
    }

    /**
     * @param $eventType
     * @param $callback
     * @param array $opts
     * @return $this
     */
    public function addListener($eventType, $callback, $opts = array())
    {
        $this->getObserver()->addListener($eventType, $callback, $opts);
        return $this;
    }

    public function setCommunicationBidirectional()
    {
        $this->_dir = self::BIDIRECTIONAL;
    }

    public function setCommunicationDirectional()
    {
        $this->_dir = self::DIRECTIONAL;
    }

    /**
     * Adding task to query.
     * Task must be added before running new process.
     * @param $task {function||array}
     */
    public function addTask(ITask $task)
    {
        array_push($this->_tasks, $task);
    }

    /**
     * main function for run worker
     * if is bidirectional messages from parent will be checked
     */
    public function run()
    {
        $this->setShmId(shm_attach(getmypid()));
        $this->fireEvent('start', array());
        if ($this->getDir() === self::BIDIRECTIONAL) {
            while (true) {
                /**
                 * Unserialize string
                 */
                if (shm_has_var($this->getShmId(), 0)) {
                    $data = shm_get_var($this->getShmId(), 0);
                    shm_remove_var($this->getShmId(), 0);
                    /**
                     * Get newest data
                     */
                    if (is_array($data)) {
                        while ($row = array_pop($data)) {
                            $this->fireEvent('message', array($row));
                        }
                    }
                    /**
                     * Wait for another message
                     */
                }
                usleep(1000);
            }
        }
        $this->fireEvent('end', array());
    }

    public function getDir()
    {
        return $this->_dir;
    }


    /**
     *
     * allow to send message to parent
     *
     * @param $data
     * @throws Exception
     */
    public function send($data)
    {
        /**
         * Open shared memory
         */
        if ($this->getShmId() === false) {
            throw new Exception(
                $this->_getError(
                    Process::COULD_NOT_CREATE_SHARED_MEMORY
                ),
                Process::COULD_NOT_CREATE_SHARED_MEMORY
            );
        }
        if (shm_has_var($this->getShmId(), 1)) {
            $shm_data = shm_get_var($this->getShmId(), 1);
            array_push($shm_data, $data);
            shm_put_var($this->getShmId(), 1, $shm_data);
        } else {
            shm_put_var($this->getShmId(), 1, array($data));
        }

    }

    /**
     * iterate over each task and run it
     * in the end send response to parent process
     * result in responses array are under task id
     *
     */
    public function startTask()
    {
        $return = array();
        foreach ($this->getTasks() as $task) {
            $return[$task->getId()] = $task->start();
        }
        $this->send($return);
    }

    public function getId()
    {
        return $this->_id;
    }

    public function getTasks()
    {
        return $this->_tasks;
    }

    public function setErrors($errors)
    {
        $this->_errors = $errors;
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    private function _getError($id)
    {
        return $this->_errors[$id];
    }

    public function setShmId($shmId)
    {
        $this->_shmId = $shmId;
    }

    public function getShmId()
    {
        return $this->_shmId;
    }


    public function __destruct()
    {
        if ($this->getShmId()) {
            shm_detach($this->getShmId());
        }
    }

}