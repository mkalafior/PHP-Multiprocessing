<?php
/**
 * Class Core_System_Process
 * @author: Sebastian Widelak
 */
class Process
{
    const PARENT = 0;
    const CHILD = 1;
    const FUNCTION_NOT_CALLABLE = 2;
    const COULD_NOT_FORK = 3;
    const COULD_NOT_CREATE_SHARED_MEMORY = 4;

    /**
     * possible errors
     *
     * @var array
     */
    private $errors = array(
        self::FUNCTION_NOT_CALLABLE => 'You must specify a valid function name that can be called from the current scope.',
        self::COULD_NOT_FORK => 'pcntl_fork() returned a status of -1. No new process was created',
        self::COULD_NOT_CREATE_SHARED_MEMORY => 'Couldn\'t create shared memory segment'
    );


    private $_shmId;

    /**
     * callback for the function that should
     * run as a separate Process
     *
     * @var callback
     */
    protected $runnable;

    /**
     * holds the current process id
     *
     * @var integer
     */
    private $pid;

    /**
     * checks if Processing is supported by the current
     * PHP configuration
     *
     * @return boolean
     */
    public static function available()
    {
        $required_functions = array(
            'pcntl_fork',
        );

        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                return false;
            }
        }

        return true;
    }

    /**
     * class constructor - you can pass
     * the callback function as an argument
     *
     * @param callback $_runnable
     */
    public function __construct($_runnable = null)
    {
        if ($_runnable !== null) {
            $this->setRunnable($_runnable);
        }
    }

    /**
     * sets the callback
     * @param $_runnable
     * @throws Exception
     *
     */
    public function setRunnable($_runnable)
    {
        if (self::runnableOk($_runnable)) {
            $this->runnable = $_runnable;
        } else {
            throw new Exception($this->getError(self::FUNCTION_NOT_CALLABLE), self::FUNCTION_NOT_CALLABLE);
        }
    }

    /**
     * gets the callback
     * @return callback
     *
     */
    public function getRunnable()
    {
        return $this->runnable;
    }

    /**
     * checks if the callback is ok (the function/method
     * actually exists and is runnable from the current
     * context)
     *
     * can be called statically
     *
     * @param callback $_runnable
     * @return boolean
     */
    public static function runnableOk($_runnable)
    {
        if (is_array($_runnable)) {
            return (method_exists($_runnable[0], $_runnable[1]) && is_callable($_runnable));
        } else {
            return (function_exists($_runnable) && is_callable($_runnable));
        }
    }

    /**
     * returns the process id (pid) of the simulated Process
     *
     * @return int
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * checks if the child Process is alive
     *
     * @return boolean
     */
    public function isAlive()
    {
        $pid = pcntl_waitpid($this->pid, $status, WNOHANG);
        return ($pid === 0);

    }

    /**
     * starts the Process, all the parameters are
     * passed to the callback function
     *
     * @throws Exception
     */
    public function start()
    {
        $pid = @ pcntl_fork();
        if ($pid == -1) {
            throw new Exception($this->getError(self::COULD_NOT_FORK), self::COULD_NOT_FORK);
        }
        if ($pid) {
            // parent
            $this->pid = $pid;
        } else {
            // child
            pcntl_signal(SIGTERM, array($this, 'signalHandler'));
            $arguments = func_get_args();
            if (!empty($arguments)) {
                call_user_func_array($this->runnable, $arguments);
            } else {
                call_user_func($this->runnable);
            }
            //shared memory
            exit(0);
        }
    }

    /**
     * attempts to stop the Process
     * returns true on success and false otherwise
     *
     * @param integer $_signal - SIGKILL/SIGTERM
     * @param boolean $_wait
     */
    public function stop($_signal = SIGKILL, $_wait = false)
    {
        if ($this->isAlive()) {
            posix_kill($this->pid, $_signal);
            if ($_wait) {
                pcntl_waitpid($this->pid, $status = 0);
            }
        }
    }

    /**
     * send data to child
     * throws an exception when cant open shared memory
     *
     * @param $data
     * @throws Exception
     */
    public function send($data)
    {
        if (!$this->getShmId())
            $this->setShmId(shm_attach($this->getPid()));
        if ($this->getShmId() === false) {
            throw new Exception(
                $this->getError(self::COULD_NOT_CREATE_SHARED_MEMORY),
                self::COULD_NOT_CREATE_SHARED_MEMORY
            );
        }
        if (shm_has_var($this->getShmId(), 0)) {
            $shm_data = shm_get_var($this->getShmId(), 0);
            array_push($shm_data, $data);
            shm_put_var($this->getShmId(), 0, $shm_data);
        } else {
            shm_put_var($this->getShmId(), 0, array($data));
        }
    }

    /**
     * gets the error's message based on
     * its id
     *
     * @param integer $_code
     * @return string
     */
    public function getError($_code)
    {
        if (isset($this->errors[$_code])) {
            return $this->errors[$_code];
        } else {
            return 'No such error code ' . $_code . '! Quit inventing errors!!!';
        }
    }

    /**
     * signal handler
     *
     * @param integer $_signal
     */
    protected function signalHandler($_signal)
    {
        switch ($_signal) {
            case SIGTERM:
                exit(0);
                break;
        }
    }

    /**
     * return data sent from worker
     * array(
     *  msgId => array (
     *      taskId => array (
     *          response
     *      )
     *  )
     * )
     * @return array
     */
    public function getMessage()
    {
        if (!$this->getShmId())
            $this->setShmId(shm_attach($this->getPid()));
        $return = array();
        /**
         * Unserialize string to array
         */
        if (shm_has_var($this->getShmId(), 1)) {
            $data = shm_get_var($this->getShmId(), 1);
            shm_remove_var($this->getShmId(), 1);
            /**
             * Get newest data
             */
            if (is_array($data)) {
                $return = $data;
            }
            /**
             * Wait for another message
             */
        }
        return $return;
    }


    public function setShmId($shmId)
    {
        $this->_shmId = $shmId;
    }

    public function getShmId()
    {
        return $this->_shmId;
    }


    /**
     * clear shared memory
     *
     */
    public function __destruct()
    {
        if ($this->getShmId()) {
            shm_remove($this->getShmId());
        }
    }
}