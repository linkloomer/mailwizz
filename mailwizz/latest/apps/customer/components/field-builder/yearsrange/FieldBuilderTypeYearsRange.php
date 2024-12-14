<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * FieldBuilderTypeYearsRange
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link https://www.mailwizz.com/
 * @copyright MailWizz EMA (https://www.mailwizz.com)
 * @license https://www.mailwizz.com/license/
 * @since 1.7.0
 */
 
class FieldBuilderTypeYearsRange extends FieldBuilderType
{
    public function run()
    {
        $apps       = Yii::app()->apps;
        $hooks      = Yii::app()->hooks;
        $baseAlias  = 'customer.components.field-builder.yearsrange.behaviors.FieldBuilderTypeYearsRange';
        $controller = Yii::app()->getController();

        // since this is a widget always running inside a controller, there is no reason for this to not be set.
        if (!$controller) {
            return;
        }
        
        $this->attachBehaviors(array(
            '_crud' => array(
                'class' => $baseAlias . 'Crud',
            ),
            '_subscriber' => array(
                'class' => $baseAlias . 'Subscriber',
            )
        ));
        
        if ($apps->isAppName('customer')) {
            
            if (in_array($controller->id, array('list_fields'))) {
                // create/view/update/delete fields
                // this event is triggered only on a post action
                $controller->callbacks->onListFieldsSave = array($this->_crud, '_saveFields');
                // this event is triggered always.
                $controller->callbacks->onListFieldsDisplay = array($this->_crud, '_displayFields');
            } elseif (in_array($controller->id, array('list_subscribers'))) {
                // this event is triggered only on a post action
                $controller->callbacks->onSubscriberSave = array($this->_subscriber, '_saveFields');
                // this event is triggered always.
                $controller->callbacks->onSubscriberFieldsDisplay = array($this->_subscriber, '_displayFields');
            }
        
        } elseif ($apps->isAppName('frontend')) {
            
            if (in_array($controller->id, array('lists'))) {
                // this event is triggered only on a post action
                $controller->callbacks->onSubscriberSave = array($this->_subscriber, '_saveFields');
                // this event is triggered always.
                $controller->callbacks->onSubscriberFieldsDisplay = array($this->_subscriber, '_displayFields');
            }
            
        }
    }
    
    public function _addInputErrorClass(CEvent $event)
    {
        if ($event->sender->owner->hasErrors($event->params['attribute'])) {
            if (!isset($event->params['htmlOptions']['class'])) {
                $event->params['htmlOptions']['class'] = '';
            }
            $event->params['htmlOptions']['class'] .= ' error';
        }
    }
    
    public function _addFieldNameClass(CEvent $event)
    {
        if (!isset($event->params['htmlOptions']['class'])) {
            $event->params['htmlOptions']['class'] = '';
        }
        $event->params['htmlOptions']['class'] .= ' field-' . strtolower($event->sender->owner->field->tag) . ' field-type-' . strtolower($event->sender->owner->field->type->identifier);
    }
}