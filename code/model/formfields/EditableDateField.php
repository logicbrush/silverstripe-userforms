<?php
/**
 * EditableDateField
 *
 * Allows a user to add a date field.
 *
 * @package userforms
 */

class EditableDateField extends EditableFormField {
	
	private static $singular_name = 'Date Field';
	
	private static $plural_name = 'Date Fields';

	private static $db = array(
		'DefaultToToday' => 'Boolean' // From customsettings
	);

	/**
	 * @return FieldList
	 */
	public function getCMSFields() {
		$this->beforeUpdateCMSFields(function(FieldList $fields) {
			$fields->addFieldToTab(
				'Root.Main',
				CheckboxField::create(
					'DefaultToToday',
					_t('EditableFormField.DEFAULTTOTODAY', 'Default to Today?')
				),
				'RightTitle'
			);
		});

		return parent::getCMSFields();
	}
	
	/**
	 * Return the form field
	 *
	 */
	public function getFormField() {
		$defaultValue = $this->DefaultToToday
			? SS_Datetime::now()->Format('Y-m-d')
			: $this->Default;
		$field = EditableDateField_FormField::create( $this->Name, $this->Title, $defaultValue);
		$field->setConfig('showcalendar', true);

		if ($this->Required) {
			// Required validation can conflict so add the Required validation messages
			// as input attributes
			$errorMessage = $this->getErrorMessage()->HTML();
			$field->setAttribute('data-rule-required', 'true');
			$field->setAttribute('data-msg-required', $errorMessage);
		}
		
		return $field;
	}
}

/**
  * @package userforms
 */
class EditableDateField_FormField extends DateField {

	public function Type() {
		return "date-alt text";
	}
}