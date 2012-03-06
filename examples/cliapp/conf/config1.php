<?php

$cfg = [];

// application-wide debug output flag
$cfg['debug'] = TRUE;

// don't load HTTP app libs during boot
$cfg['httpBundle'] = FALSE;

// specify namespace paths for class autoloaders
$cfg['namespaces'] = [
  '' => AX_APP_PATH . '/src'
];

// specify event listeners
$cfg['listeners'] = [
  
  ['app.shutdown', function() {
    echo PHP_EOL . '... app.shutdown ...' . PHP_EOL;
  }],
  
  ['app.exception', function(\Exception $e) {
    $handler = $this->depProvider->make('controllers.ExHandler');
    $handler->setException($e)->exec()->getResponse()->output();
    throw new artax\exceptions\ScriptHaltException;
  }],
  
  ['app.ready', function() {
    echo PHP_EOL . '... app.ready ...' . PHP_EOL . PHP_EOL;
    $this->notify('app.questions');
  }],
  ['app.questions', function() {
    echo 'app.questions: What is your name?' . PHP_EOL;
    $this->notify('app.quest');
  }],
  ['app.questions', function() {
    echo 'app.questions: What is your quest?' . PHP_EOL;
    $this->notify('app.color');
  }],
  ['app.questions', function() {
    echo 'app.questions: What is your favorite color?' . PHP_EOL;
    $this->notify('app.swallow');
    return FALSE;
  }],
  ['app.questions', function() {
    echo 'app.questions: What is the airspeed velocity of an unladen swallow?' . PHP_EOL;
  }]
];