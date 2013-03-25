<?php
/**
 */
interface IObservable
{
    public function setObserver(Observer $observer);

    public function getObserver();

    public function fireEvent($eventType, $args);

    public function addListener($eventType, $callback, $opts = array());


}
