<?php

namespace LeKoala\MultiStepForm;

use SilverStripe\Forms\FormRequestHandler;

class MultiStepFormRequestHandler extends FormRequestHandler
{

    /**
     * Form model being handled
     *
     * @var MultiStepForm
     */
    protected $form = null;

    /**
     * @config
     * @var array
     */
    private static $allowed_actions = [
        'handleField',
        'httpSubmission',
        'forTemplate',
        'gotoStep',
    ];

    public function checkAccessAction($action)
    {
        if ($action === 'gotoStep') {
            return true;
        }
        return parent::checkAccessAction($action);
    }

    /**
     * @param HTTPRequest $request
     * @return HTTPResponse
     * @throws HTTPResponse_Exception
     */
    public function gotoStep($request)
    {
        return $this->form->gotoStep();
    }
}
