<?php
/**
 * User: sebastian
 * Date: 31.07.12
 * Time: 14:01
 */
class Observer
{

    /**
     * @var array
     */
    private $_listeners = array();

    /**
     * @param $eventType
     * @param $callback
     * @param array $opts
     */
    public function addListener($eventType, $callback, $opts = array())
    {
        if (!array_key_exists($eventType, $this->_listeners))
            $this->_listeners[$eventType] = array();
        array_push($this->_listeners[$eventType], array('callback' => $callback, 'opts' => $opts));
    }

    /**
     * @param $eventType
     * @param $args
     */
    public function fireEvent($eventType, $args)
    {
        if (!is_array($args))
            $args = array($args);
        if (array_key_exists($eventType, $this->_listeners))
            foreach ($this->_listeners[$eventType] as $index => $listener) {
                if ($listener['callback'] instanceof Closure) {
                    call_user_func_array($listener['callback'], $args);
                    if (array_key_exists('single', $listener['opts']) && $listener['opts']['single'] === true) {
                        $this->_listeners[$eventType][$index] = null;
                        unset($this->_listeners[$eventType][$index]);
                    }
                } else if (is_array($listener['callback'])) {
                    call_user_func_array(array($listener['callback'][0], $listener['callback'][1]), $args);
                    if (array_key_exists('single', $listener['opts']) && $listener['opts']['single'] === true) {
                        $this->_listeners[$eventType][$index] = null;
                        unset($this->_listeners[$eventType][$index]);
                    }
                }
            }
    }

}
