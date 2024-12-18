<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * FieldBuilderTypePhonenumberSubscriber
 * 
 * The save action is running inside an active transaction.
 * For fatal errors, an exception must be thrown, otherwise the errors array must be populated.
 * If an exception is thrown, or the errors array is populated, the transaction is rolled back.
 * 
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com> 
 * @link https://www.mailwizz.com/
 * @copyright MailWizz EMA (https://www.mailwizz.com)
 * @license https://www.mailwizz.com/license/
 * @since 1.8.1
 */
 
class FieldBuilderTypePhonenumberSubscriber extends CBehavior
{
    // register at init
    public function attach($owner)
    {
        parent::attach($owner);

        // register the javascript code
        $options = CJavaScript::jsonEncode(array(
            'utilsScript'     => Yii::app()->apps->getAppUrl('frontend', 'assets/js/intl-tel-input/js/utils.js', false, true),
            'autoPlaceholder' => 'aggressive',
            'hiddenInput'     => 'full'
        ));

        $errorsMap = CJavaScript::jsonEncode(array(
            Yii::t('list_fields', 'Invalid number'),
            Yii::t('list_fields', 'Invalid country code'),
            Yii::t('list_fields', 'Too short'),
            Yii::t('list_fields', 'Too long'),
            Yii::t('list_fields', 'Invalid number')
        ));

        Yii::app()->clientScript->registerScript(sha1(__METHOD__), '
            window.fieldTypePhoneNumberOptions = ' . $options . ';
            window.fieldTypePhoneNumberErrorMap = ' . $errorsMap . ';
        ', CClientScript::POS_HEAD);
        Yii::app()->clientScript->registerScriptFile(Yii::app()->apps->getAppUrl('frontend', 'assets/js/intl-tel-input/js/custom.js', false, true));

    }

    /**
     * @param CEvent $event
     * @throws CException
     */
    public function _saveFields(CEvent $event)
    {
        $models = array();
        
        $fieldType   = $this->owner->getFieldType();
        $list        = $this->owner->getList();
        $subscriber  = $this->owner->getSubscriber();
        $typeName    = $fieldType->identifier;
        $request     = Yii::app()->request;
        $valueModels = $this->getValueModels();
        $fields      = array();
        
        if (!isset($event->params['fields'][$typeName]) || !is_array($event->params['fields'][$typeName])) {
            $event->params['fields'][$typeName] = array();
        }
        
        // run validation so that fields will get the errors if any.
        foreach ($valueModels as $model) {
            if (!$model->validate()) {
                $this->owner->errors[] = array(
                    'show'      => false, 
                    'message'   => $model->shortErrors->getAllAsString()
                );
            }
            $fields[] = $this->buildFieldArray($model);
        }
        
        // make the fields available
        $event->params['fields'][$typeName] = $fields;
        
        // do the actual saving of fields if there are no errors.
        if (empty($this->owner->errors)) {
            foreach ($valueModels as $model) {
                $model->save(false);
            }    
        }
    }

    /**
     * @param CEvent $event
     * @throws CException
     */
    public function _displayFields(CEvent $event)
    {
        $fieldType   = $this->owner->getFieldType();
        $typeName    = $fieldType->identifier;
        $list        = $this->owner->getList();
        
        // fields created in the save action.
        if (isset($event->params['fields'][$typeName]) && is_array($event->params['fields'][$typeName])) {
            return;
        }

        if (!isset($event->params['fields'][$typeName]) || !is_array($event->params['fields'][$typeName])) {
            $event->params['fields'][$typeName] = array();
        }
        
        $valueModels = $this->getValueModels();
        $fields      = array();

        foreach ($valueModels as $model) {
            $fields[] = $this->buildFieldArray($model);
        }

        $event->params['fields'][$typeName] = $fields;
    }

    /**
     * @return array
     * @throws CException
     */
    protected function getValueModels()
    {
        $fieldType  = $this->owner->getFieldType();
        $list       = $this->owner->getList();
        $subscriber = $this->owner->getSubscriber();
        $request    = Yii::app()->request;
        $appParams  = Yii::app()->params;
        $ioFilter   = Yii::app()->ioFilter;
        
        $models = ListField::model()->findAllByAttributes(array(
            'type_id' => (int)$fieldType->type_id,
            'list_id' => (int)$list->list_id,
        ));
        
        $valueModels = array();
        foreach ($models as $model) {
            $valueModel = ListFieldValue::model()->findByAttributes(array(
                'field_id'      => (int)$model->field_id,
                'subscriber_id' => (int)$subscriber->subscriber_id,
            ));
            
            if (empty($valueModel)) {
                $valueModel = new ListFieldValue();    
            }

            // setup rules and labels here.
            $valueModel->onAttributeLabels                  = array($this, '_setCorrectLabel');
            $valueModel->onRules                            = array($this, '_setCorrectValidationRules');
            $valueModel->onAttributeHelpTexts               = array($this, '_setCorrectHelpText');
            $valueModel->fieldDecorator->onHtmlOptionsSetup = array($this->owner, '_addInputErrorClass');
            $valueModel->fieldDecorator->onHtmlOptionsSetup = array($this->owner, '_addFieldNameClass');

	        // set the correct default value.
	        $defaultValue = empty($valueModel->value) ? ListField::parseDefaultValueTags($model->default_value, $subscriber) : $valueModel->value;
	        
            // assign props
            $valueModel->field          = $model;
            $valueModel->field_id       = $model->field_id;
            $valueModel->subscriber_id  = $subscriber->subscriber_id;
            $valueModel->value          = $request->getPost($model->tag, $defaultValue);
            
            $valueModels[] = $valueModel;
        }
        
        return $valueModels;
    }

    /**
     * @param $model
     * @return array
     * @throws CException
     */
    protected function buildFieldArray($model)
    {
        $field      = $model->field;
        $fieldHtml  = null;
        $viewFile   = realpath(dirname(__FILE__) . '/../views/field-display.php');

        // NOTE: maybe this should go into the view file with a display:none style ? 
        if ($field->visibility == ListField::VISIBILITY_VISIBLE || Yii::app()->apps->isAppName('customer')) {
            $fieldHtml = $this->owner->renderInternal($viewFile, compact('model', 'field'), true);
        }

        return array(
            'sort_order' => (int)$field->sort_order,
            'field_html' => $fieldHtml,
        );
    }

    /**
     * @param CModelEvent $event
     */
    public function _setCorrectLabel(CModelEvent $event)
    {
        $event->params['labels']['value'] = $event->sender->field->label;    
    }

    /**
     * @param CModelEvent $event
     */
    public function _setCorrectValidationRules(CModelEvent $event)
    {
        // get the CList instance of rules.
        $rules = $event->params['rules'];
        
        // clear any other rule we have so far
        $rules->clear();
        
        // start adding new rules.
        if ($event->sender->field->required === 'yes') {
            $rules->add(array('value', 'required'));
        }

        $rules->add(array('value', 'length', 'min' => 3, 'max' => 50));
    }

    /**
     * @param CModelEvent $event
     */
    public function _setCorrectHelpText(CModelEvent $event)
    {
        $event->params['texts']['value'] = $event->sender->field->help_text;
    }
}