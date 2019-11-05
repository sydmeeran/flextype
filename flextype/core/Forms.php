<?php

declare(strict_types=1);

/**
 * Flextype (http://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Flextype\Component\Arr\Arr;
use Flextype\Component\Form\Form;
use Psr\Http\Message\ServerRequestInterface as Request;
use function count;
use function date;
use function Flextype\Component\I18n\__;
use function str_replace;
use function strlen;
use function strpos;
use function strtotime;
use function substr_replace;

class Forms
{
    /**
     * Flextype Dependency Container
     *
     * @var
     * @access private
     */
    private $flextype;

    /**
     * Form controls sizes
     *
     * @var array
     * @access private
     */
    private $sizes = [
        '1/12' => 'col-1',
        '2/12' => 'col-2',
        '3/12' => 'col-3',
        '4/12' => 'col-4',
        '5/12' => 'col-5',
        '6/12' => 'col-6',
        '7/12' => 'col-7',
        '8/12' => 'col-8',
        '9/12' => 'col-9',
        '10/12' => 'col-19',
        '12/12' => 'col-11',
        '12' => 'col-12',
    ];

    /**
     * Field class
     *
     * @var string
     * @access private
     */
    private $field_class = 'form-control';

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct($flextype)
    {
        $this->flextype = $flextype;
    }

    /**
     * Render form
     *
     * @param array   $fieldset Fieldset
     * @param array   $values   Fieldset values
     * @param Request $request  PSR7 request
     *
     * @return string Returns form based on fieldsets
     *
     * @access public
     */
    public function render(array $fieldset, array $values = [], Request $request) : string
    {
        $form  = Form::open(null, ['id' => 'form']);
        $form .= $this->_csrfHiddenField();
        $form .= $this->_actionHiddenField();

        // Go through all sections
        if (count($fieldset['sections']) > 0) {
            $form .= '<ul class="nav nav-pills nav-justified" id="pills-tab" role="tablist">';

            // Go through all sections and create nav items
            foreach ($fieldset['sections'] as $key => $section) {
                $form .= '<li class="nav-item">
                            <a class="nav-link ' . ($key === 'main' ? 'active' : '') . '"
                               id="pills-' . $key . '-tab"
                               data-toggle="pill" href="#pills-' . $key . '"
                               role="tab"
                               aria-controls="pills-' . $key . '"
                               aria-selected="' . ($key === 'main' ? 'true' : 'false') . '">' . $section['title'] . '</a>
                          </li>';
            }

            $form .= '</ul>';

            $form .= '<div class="tab-content" id="pills-tabContent">';

            // Go through all sections and create nav tabs
            foreach ($fieldset['sections'] as $key => $section) {
                $form .= '<div class="tab-pane fade  ' . ($key === 'main' ? 'show active' : '') . '" id="pills-' . $key . '" role="tabpanel" aria-labelledby="pills-' . $key . '-tab">';
                $form .= '<div class="row">';

                foreach ($section['fields'] as $element => $property) {
                    // Create attributes
                    $property['attributes'] = Arr::keyExists($property, 'attributes') ? $property['attributes'] : [];

                    // Create attribute class
                    $property['attributes']['class'] = Arr::keyExists($property, 'attributes.class') ? $this->field_class . ' ' . $property['attributes']['class'] : $this->field_class;

                    // Create attribute size
                    $property['size'] = Arr::keyExists($property, 'size') ? $this->sizes[$property['size']] : $this->sizes['12'];

                    // Create attribute value
                    $property['value'] = Arr::keyExists($property, 'value') ? $property['value'] : '';

                    // Create title label
                    $property['title'] = Arr::keyExists($property, 'title') ? $property['title'] : false;

                    // Set element name
                    $element_name = $this->getElementName($element);

                    // Set element value
                    $form_value = Arr::keyExists($values, $element) ? Arr::get($values, $element) : $property['value'];

                    // Set form element
                    $form_element = '';

                    // Form elements
                    switch ($property['type']) {
                        // Simple text-input, for multi-line fields.
                        case 'textarea':
                            $form_element = $this->textareaField($element, $element_name, $form_value, $property);
                            break;
                        // The hidden field is like the text field, except it's hidden from the content editor.
                        case 'hidden':
                            $form_element = $this->hiddenField($element_name, $form_value, $property);
                            break;
                        // A WYSIWYG HTML field.
                        case 'html':
                            $form_element = $this->htmlField($element, $element_name, $form_value, $property);
                            break;
                        // Selectbox field
                        case 'select':
                            $form_element = $this->selectField($element, $element_name, $property['options'], $form_value, $property);
                            break;
                        // Template select field for selecting entry template
                        case 'template_select':
                            $form_element = $this->templateSelectField($element, $element_name, $form_value, $property);
                            break;
                        // Visibility select field for selecting entry visibility state
                        case 'visibility_select':
                            $form_element = $this->visibilitySelectField($element, $element_name, ['draft' => __('admin_entries_draft'), 'visible' => __('admin_entries_visible'), 'hidden' => __('admin_entries_hidden')], (! empty($form_value) ? $form_value : 'visible'), $property);
                            break;
                        case 'tags':
                            $form_element = $this->tagsField($element, $element_name, $form_value, $property);
                            break;
                        case 'datetimepicker':
                            $form_element = $this->dateField($element, $element_name, $form_value, $property);
                            break;
                        case 'media_select':
                            $form_element = $this->mediaSelectField($element, $element_name, $this->flextype->EntriesController->getMediaList($request->getQueryParams()['id'], false), $form_value, $property);
                            break;
                        // Simple text-input, for single-line fields.
                        default:
                            $form_element = $this->textField($element, $element_name, $form_value, $property);
                            break;
                    }

                    $form .= $form_element;

                }
                $form .= '</div>';
                $form .= '</div>';
            }
            $form .= '</div>';
        }

        $form .= Form::close();

        return $form;
    }

    /**
     * Get element name
     *
     * @param string $element Element
     *
     * @return string Returns form element name
     *
     * @access protected
     */
    protected function getElementName(string $element) : string
    {
        $pos = strpos($element, '.');

        if ($pos === false) {
            $element_name = $element;
        } else {
            $element_name = str_replace('.', '][', "$element") . ']';
        }

        $pos = strpos($element_name, ']');

        if ($pos !== false) {
            $element_name = substr_replace($element_name, '', $pos, strlen(']'));
        }

        return $element_name;
    }

    /**
     * Media select field
     *
     * @param string $name     Field name
     * @param array  $options  Field options
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function mediaSelectField(string $element, string $name, array $options, string $value, array $property) : string
    {
        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= Form::select($name, $options, $value, $property['attributes']);
        $field .= '</div>';

        return $field;
    }

    /**
     * Template select field
     *
     * @param string $name     Field name
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function templateSelectField(string $element, string $name, string $value, array $property) : string
    {
        $_templates_list = $this->flextype['themes']->getTemplates($this->flextype['registry']->get('settings.theme'));

        if (count($_templates_list) > 0) {
            foreach ($_templates_list as $template) {
                if ($template['type'] !== 'file' || $template['extension'] !== 'html') {
                    continue;
                }

                $options[$template['basename']] = $template['basename'];
            }
        }

        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= Form::select($name, $options, $value, $property['attributes']);
        $field .= '</div>';

        return $field;
    }

    /**
     * Select field
     *
     * @param string $name     Field name
     * @param array  $options  Field options
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function selectField(string $element, string $name, array $options, string $value, array $property) : string
    {
        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= Form::select($name, $options, $value, $property['attributes']);
        $field .= '</div>';

        return $field;
    }

    /**
     * Html field
     *
     * @param string $name     Field name
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function htmlField(string $element, string $name, string $value, array $property) : string
    {
        $property['attributes']['class'] .= ' js-html-editor';

        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= Form::textarea($name, $value, $property['attributes']);
        $field .= '</div>';

        return $field;
    }

    /**
     * Hidden field
     *
     * @param string $name     Field name
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function hiddenField(string $name, string $value, array $property) : string
    {
        return Form::hidden($name, $value, $property['attributes']);
    }

    /**
     * Textarea field
     *
     * @param string $name     Field name
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function textareaField(string $element, string $name, string $value, array $property) : string
    {
        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= Form::textarea($name, $value, $property['attributes']);
        $field .= '</div>';

        return $field;
    }

    /**
     * Visibility field
     *
     * @param string $name     Field name
     * @param array  $options  Field options
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function visibilitySelectField(string $element, string $name, array $options, string $value, array $property) : string
    {
        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= Form::select($name, $options, $value, $property['attributes']);
        $field .= '</div>';

        return $field;
    }

    /**
     * Text field
     *
     * @param string $name     Field name
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function textField(string $element, string $name, string $value, array $property) : string
    {
        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= Form::input($name, $value, $property['attributes']);
        $field .= '</div>';

        return $field;
    }

    /**
     * Tags field
     *
     * @param string $name  Field name
     * @param string $value Field value
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function tagsField(string $element, string $name, string $value, array $property) : string
    {
        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= '<input type="text" value="' . $value . '" name="' . $name . '" class="form-control" data-role="tagsinput" />';
        $field .= '</div>';

        return $field;
    }

    /**
     * Date field
     *
     * @param string $name     Field name
     * @param string $value    Field value
     * @param array  $property Field property
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function dateField(string $element, string $name, string $value, array $property) : string
    {
        $field = '<div class="form-group ' . $property['size'] . '">';
        $field .= ($property['title'] ? Form::label($element, __($property['title'])) : '');
        $field .= '
            <div class="input-group date" id="datetimepicker" data-target-input="nearest">
                <input name="' . $name . '" type="text" class="form-control datetimepicker-input" data-target="#datetimepicker" value="' . date($this->flextype->registry->get('settings.date_format'), strtotime($value)) . '" />
                <div class="input-group-append" data-target="#datetimepicker" data-toggle="datetimepicker">
                    <div class="input-group-text"><i class="far fa-calendar-alt"></i></div>
                </div>
            </div>
        ';
        $field .= '</div>';

        return $field;
    }

    /**
     * _csrfHiddenField
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function _csrfHiddenField() : string
    {
        $field  = '<input type="hidden" name="' . $this->flextype['csrf']->getTokenNameKey() . '" value="' . $this->flextype['csrf']->getTokenName() . '">';
        $field .= '<input type="hidden" name="' . $this->flextype['csrf']->getTokenValueKey() . '" value="' . $this->flextype['csrf']->getTokenValue() . '">';

        return $field;
    }

    /**
     * _actionHiddenField
     *
     * @return string Returns field
     *
     * @access protected
     */
    protected function _actionHiddenField() : string
    {
        return Form::hidden('action', 'save-form');
    }
}
