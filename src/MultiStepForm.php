<?php

namespace LeKoala\MultiStepForm;

use Exception;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\Form;
use InvalidArgumentException;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;
use SilverStripe\Control\Session;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\Validator;
use SilverStripe\Forms\FormAction;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Control\RequestHandler;
use SilverStripe\ORM\ValidationException;

/**
 * Multi step form
 *
 * - Define a class name with a number in it (MyFormStep1) that extends this class
 * - Call definePrevNextActions instead of defining your actions
 * - Define a name in getStepTitle for a nicer name
 * - In your controller, create the form with classForCurrentStep
 *
 * @author lekoala
 */
abstract class MultiStepForm extends Form
{
    private static $include_css = true;
    private static $class_active = "current bg-primary text-white";
    private static $class_inactive = "link";
    private static $class_completed = "msf-completed bg-primary text-white";
    private static $class_not_completed = "msf-not-completed bg-light text-muted";

    protected $validationExemptActions = ["doPrev"];

    /**
     * @param RequestHandler $controller
     * @param mixed $name Extended to allow passing objects directly
     * @param FieldList $fields
     * @param FieldList $actions
     * @param Validator $validator
     */
    public function __construct(
        RequestHandler $controller = null,
        $name = null,
        FieldList $fields = null,
        FieldList $actions = null,
        Validator $validator = null
    ) {
        // Set a default name
        if (!$name) {
            $name = self::classNameWithoutNumber();
        }
        if ($fields) {
            throw new InvalidArgumentException("Fields should be defined inside MultiStepForm::buildFields method");
        }
        if ($actions) {
            throw new InvalidArgumentException("Actions are automatically defined by MultiStepForm");
        }
        if ($validator) {
            throw new InvalidArgumentException("Validator should be defined inside MultiStepForm::buildValidator method");
        }
        $this->setController($controller);
        $fields = $this->buildFields();
        $actions = $this->buildActions();
        $validator = $this->buildValidator($fields);
        parent::__construct($controller, $name, $fields, $actions, $validator);

        if (self::config()->include_css) {
            Requirements::css("lekoala/silverstripe-multi-step-form:css/multi-step-form.css");
        }

        // Loads first submitted data
        $data = $this->getTempDataFromSession();
        if (!empty($data)) {
            $this->loadDataFrom($data);
        } else {
            $this->restoreData();
        }
    }

    /**
     * @return FieldList
     */
    abstract protected function buildFields();

    /**
     * Call this instead of manually creating your actions
     *
     * You can easily rename actions by calling $actions->fieldByName('action_doNext')->setTitle('...')
     *
     * @return FieldList
     */
    protected function buildActions()
    {
        $actions = new FieldList();

        $prev = null;
        if (self::classNameNumber() > 1) {
            $prevLabel = _t('MultiStepForm.doPrev', 'Previous');
            $actions->push($prev = new FormAction('doPrev', $prevLabel));
            $prev->setUseButtonTag(true);
            // this must be supported by your validation client, it works with Zenvalidator
            $prev->addExtraClass("ignore-validation");
            $prev->addExtraClass("msf-step-prev");
        }

        $label = _t('MultiStepForm.doNext', 'Next');
        $actions->push($next = new FormAction('doNext', $label));
        $next->setUseButtonTag(true);
        $next->addExtraClass('msf-step-next');
        if (!$prev) {
            $next->addExtraClass('msf-step-next-single');
        }
        if (self::isLastStep()) {
            $next->setTitle(_t('MultiStepForm.doFinish', 'Finish'));
            $next->addExtraClass('msf-step-last');
        }

        if ($prev) {
            $actions->push($prev);
        }

        $this->addExtraClass('msf');

        return $actions;
    }

    /**
     * @param FieldList $fields
     * @return Validator
     */
    protected function buildValidator(FieldList $fields)
    {
        return new RequiredFields;
    }

    public function FormAction()
    {
        $action = parent::FormAction();
        $action .= '?step=' . self::classNameNumber();
        return $action;
    }

    /**
     * Get a class name without namespace
     * @return string
     */
    public static function getClassWithoutNamespace()
    {
        $parts = explode("\\", get_called_class());
        return array_pop($parts);
    }

    /**
     * Get class name without any number in it
     * @return string
     */
    public static function classNameWithoutNumber()
    {
        return preg_replace('/[0-9]+/', '', self::getClassWithoutNamespace());
    }

    /**
     * Get number from class name
     * @return string
     */
    public static function classNameNumber()
    {
        return preg_replace('/[^0-9]+/', '', self::getClassWithoutNamespace());
    }

    /**
     * Get class name for current step based on this class name
     * @param Controller $controller
     * @return string
     */
    public static function classForCurrentStep($controller = null)
    {
        if (!$controller) {
            $controller = Controller::curr();
        }

        $request = $controller->getRequest();

        // Defaults to step 1
        $step = 1;

        // Check session
        $sessionStep = self::getCurrentStep();
        if ($sessionStep) {
            $step = $sessionStep;
        }
        // Override with step set manually
        $requestStep = (int)$request->getVar('step');
        if ($requestStep) {
            $step = $requestStep;
        }

        return str_replace(self::classNameNumber(), $step, self::getClassWithoutNamespace());
    }

    /**
     * Get all steps as an ArrayList. To be used for your templates.
     * @return ArrayList
     */
    public function AllSteps()
    {
        $num = self::classNameNumber();
        if (!$num) {
            return;
        }
        $controller = Controller::curr();
        $n = 1;
        $curr = self::getCurrentStep($controller->getRequest()->getSession());
        if (!$curr) {
            $curr = 1;
        }
        $class = str_replace($num, $n, self::getClassWithoutNamespace());
        $steps = new ArrayList();

        $baseAction = parent::FormAction();
        $config = self::config();

        while (class_exists($class)) {
            $isCurrent = $isCompleted = $isNotCompleted = false;
            $cssClass = $n == $curr ? $config->class_active : $config->class_inactive;
            if ($n == 1) {
                $isCurrent = true;
                $cssClass .= ' first';
            }
            if ($class::isLastStep()) {
                $cssClass .= ' last';
            }
            if ($n < $curr) {
                $isCompleted = true;
                $cssClass .= ' ' . $config->class_completed;
            }
            if ($n > $curr) {
                $isNotCompleted = true;
                $cssClass .= ' ' . $config->class_not_completed;
            }
            $link = rtrim($baseAction, '/') . '/gotoStep/?step=' . $n;
            $steps->push(new ArrayData(array(
                'Title' => $class::getStepTitle(),
                'Number' => $n,
                'Link' => $isNotCompleted ? null : $link,
                'Class' => $cssClass,
                'IsCurrent' => $isCurrent,
                'IsCompleted' => $isCompleted,
                'isNotCompleted' => $isNotCompleted,
            )));
            $n++;
            $class = str_replace(self::classNameNumber(), $n, self::getClassWithoutNamespace());
        }
        return $steps;
    }

    /**
     * @return DBHTMLText
     */
    public function DisplaySteps()
    {
        return $this->renderWith('MsfSteps');
    }

    /**
     * Clear current step
     * @return void
     */
    public static function clearCurrentStep()
    {
        $session = self::getStaticSession();
        $session->clear(self::classNameWithoutNumber() . '.step');
    }

    /**
     * Get current step (defined in session). 0 if not started yet.
     * @return int
     */
    public static function getCurrentStep()
    {
        $session = self::getStaticSession();
        return (int) $session->get(self::classNameWithoutNumber() . '.step');
    }

    /**
     * Set max step
     * @param int $value
     * @return void
     */
    public static function setMaxStep($value)
    {
        $session = self::getStaticSession();
        $session->set(self::classNameWithoutNumber() . '.maxStep', (int) $value);
    }

    /**
     * Get max step (defined in session). 0 if not started yet.
     * @return int
     */
    public static function getMaxStep()
    {
        $session = self::getStaticSession();
        return (int) $session->get(self::classNameWithoutNumber() . '.maxStep');
    }

    /**
     * Set current step
     * @param int $value
     * @return void
     */
    public static function setCurrentStep($value)
    {
        $session = self::getStaticSession();
        $value = (int) $value;

        // Track highest step for step navigation
        if ($value > self::getMaxStep()) {
            self::setMaxStep($value);
        }
        $session->set(self::classNameWithoutNumber() . '.step', $value);
    }

    /**
     * @return int
     */
    public static function getStepsCount()
    {
        $class = self::classNameWithoutNumber();
        $i = 1;
        $stepClass = $class . $i;
        while (class_exists($stepClass)) {
            $i++;
            $stepClass = $class . $i;
        }
        return --$i;
    }

    /**
     * Increment step
     * @return string
     */
    public static function incrementStep()
    {
        if (self::isLastStep()) {
            return;
        }
        $next = self::classNameNumber() + 1;
        if ($next == 1) {
            $next++;
        }
        return self::setCurrentStep($next);
    }

    /**
     * Decrement step
     * @return string
     */
    public static function decrementStep()
    {
        $session = self::getStaticSession();
        $prev = self::classNameNumber() - 1;
        if ($prev < 1) {
            return;
        }
        return self::setCurrentStep($prev);
    }

    /**
     * Go to a step
     * @return HTTPResponse
     */
    public function gotoStep()
    {
        $step = $this->getController()->getRequest()->getVar('step');
        if ($step > 0 && $step <= self::getMaxStep()) {
            self::setCurrentStep($step);
        }
        return $this->getController()->redirectBack();
    }

    /**
     * Check if this is the last step
     * @return bool
     */
    public static function isLastStep()
    {
        $n = self::classNameNumber();
        $n1 = $n + 1;
        $class = str_replace($n, $n1, self::getClassWithoutNamespace());
        return !class_exists($class);
    }

    /**
     * Return the step name
     * @return string
     */
    abstract public static function getStepTitle();

    /**
     * Can be overwritten in child classes to update submitted data
     *
     * @param array $data
     * @return array
     */
    protected function processData(array $data)
    {
        return $data;
    }

    /**
     * @return bool
     */
    protected function restoreData()
    {
        $data = $this->getDataFromSession();
        if (!empty($data)) {
            $this->loadDataFrom($data);
            return true;
        }
        return false;
    }


    protected function persistData(array $data = [])
    {
        $this->saveDataInSession($data);
    }

    /**
     * Can be overwritten in child classes to apply custom step validation
     *
     * @throws ValidationException
     * @param array $data
     * @return void
     */
    protected function validateData(array $data)
    {
    }

    /**
     * @return Session
     */
    public static function getStaticSession()
    {
        return Controller::curr()->getRequest()->getSession();
    }

    /**
     * @param Controller $controller
     * @return Session
     */
    public function getSession($controller = null)
    {
        if ($controller === null) {
            $controller = $this->getController();
        }
        if ($controller) {
            return $controller->getRequest()->getSession();
        }
        return self::getStaticSession();
    }

    /**
     * A basic previous action that decrements the current step
     * @param array $data
     * @return HTTPResponse
     */
    public function doPrev($data)
    {
        $controller = $this->getController();
        self::decrementStep($this->getSession());
        return $controller->redirectBack();
    }

    /**
     * A basic next action that increments the current step and save the data to the session
     * @param array $data
     * @return HTTPResponse
     */
    public function doNext($data)
    {
        $controller = $this->getController();

        try {
            $this->validateData($data);
        } catch (ValidationException $ex) {
            $this->saveTempDataInSession($data);
            $this->sessionError($ex->getMessage());
            return $controller->redirectBack();
        }

        $data = $this->processData($data);

        self::incrementStep();
        $this->clearTempDataFromSession();

        try {
            $this->persistData($data);
        } catch (Exception $ex) {
            $this->sessionError($ex->getMessage());
            return $controller->redirectBack();
        }

        if (self::isLastStep()) {
            // You will need to clear the current step and redirect to something else on the last step
            throw new Exception("Not implemented: please override doNext in your class for last step");
        }

        return $controller->redirectBack();
    }

    /**
     * @param int $step
     * @return array
     */
    public static function getDataFromStep($step)
    {
        $session = self::getStaticSession();
        return $session->get(self::classNameWithoutNumber() . ".step_" . $step);
    }

    /**
     * @param array $data
     */
    public function saveDataInSession(array $data = null)
    {
        $session = $this->getSession();
        if (!$data) {
            $data = $this->getData();
        }
        $session->set(
            self::classNameWithoutNumber() . ".step_" . self::classNameNumber(),
            $data
        );
    }

    /**
     * @param array $data
     */
    public function saveTempDataInSession(array $data = null)
    {
        $session = $this->getSession();
        if (!$data) {
            $data = $this->getData();
        }
        $session->set(
            self::classNameWithoutNumber() . ".temp",
            $data
        );
    }

    /**
     * @return array
     */
    public function getDataFromSession()
    {
        $session = $this->getSession();
        return $session->get(self::classNameWithoutNumber() . ".step_" . self::classNameNumber());
    }

    /**
     * This is the data as submitted by the user
     *
     * @return array
     */
    public function getTempDataFromSession()
    {
        $session = $this->getSession();
        return $session->get(self::classNameWithoutNumber() . ".temp");
    }

    /**
     * @param boolean $merge Merge everything into a flat array (true by default) or return a multi dimensional array
     * @return array
     */
    public static function getAllDataFromSession($merge = true)
    {
        $session = self::getStaticSession();
        $arr = [];
        $class = self::classNameWithoutNumber();
        foreach (range(1, self::getStepsCount()) as $i) {
            if ($merge) {
                $step = $session->get($class . ".step_" . $i);
                if ($step) {
                    $arr = array_merge($arr, $step);
                }
            } else {
                $arr[$i] = $session->get($class . ".step_" . $i);
            }
        }
        return $arr;
    }

    /**
     * Utility to quickly scaffold cms facing fields
     *
     * @param FieldList $fields
     * @param array $data
     * @param array $ignore
     * @return void
     */
    public static function getAsTabbedFields(FieldList $fields, $data = [], $ignore = [])
    {
        $controller = Controller::curr();
        $class = self::classNameWithoutNumber();
        foreach (range(1, self::getStepsCount()) as $i) {
            $classname = $class . $i;
            $inst = new $classname($controller);

            $stepFields = $inst->Fields();

            $tab = new Tab('Step' . $i, $i . " - " . $inst->getStepTitle());
            $fields->addFieldToTab('Root', $tab);

            /** @var FormField $sf */
            foreach ($stepFields as $sf) {
                $name = $sf->getName();
                if (in_array($name, $ignore)) {
                    continue;
                }

                $sf->setReadonly(true);
                // $sf->setDisabled(true);
                if (!empty($data[$name])) {
                    $sf->setValue($data[$name]);
                }

                if ($sf instanceof CompositeField) {
                    foreach ($sf->getChildren() as $child) {
                        $childName = $child->getName();
                        if (in_array($childName, $ignore)) {
                            continue;
                        }

                        if (!empty($data[$childName])) {
                            $child->setValue($data[$childName]);
                        }
                    }
                }

                $tab->push($sf);
            }
        }
    }

    /**
     * @param int $step
     * @return array
     */
    public function clearTempDataFromSession()
    {
        $session = $this->getSession();
        return $session->clear(self::classNameWithoutNumber() . ".temp");
    }

    /**
     * @param int $step
     * @return array
     */
    public function clearDataFromSession()
    {
        $session = $this->getSession();
        return $session->clear(self::classNameWithoutNumber() . ".step_" . self::classNameNumber());
    }

    /**
     * Clear all infos stored in the session from all steps
     */
    public function clearAllDataFromSession()
    {
        $session = $this->getSession();
        self::clearCurrentStep();
        $session->clear(self::classNameWithoutNumber());
    }

    public function buildRequestHandler()
    {
        return new MultiStepFormRequestHandler($this);
    }
}
