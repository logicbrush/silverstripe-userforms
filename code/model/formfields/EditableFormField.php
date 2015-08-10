<?php
/**
 * Represents the base class of a editable form field 
 * object like {@link EditableTextField}. 
 *
 * @package userforms
 * @method DataList DisplayRules() List of EditableCustomRule objects
 */
class EditableFormField extends DataObject {

	/**
	 * Default sort order
	 *
	 * @config
	 * @var string
	 */
	private static $default_sort = '"Sort"';
	
	/**
	 * A list of CSS classes that can be added
	 *
	 * @var array
	 */
	public static $allowed_css = array();

	/**
	 * @config
	 * @var array
	 */
	private static $summary_fields = array(
		'Title'
	);

	/**
	 * @config
	 * @var array
	 */
	private static $db = array(
		"Name" => "Varchar",
		"Title" => "Varchar(255)",
		"Default" => "Varchar(255)",
		"Sort" => "Int",
		"Required" => "Boolean",
		"CustomErrorMessage" => "Varchar(255)",

		"CustomRules" => "Text", // @deprecated from 2.0
		"CustomSettings" => "Text", // @deprecated from 2.0
		"Migrated" => "Boolean", // set to true when migrated

		"ExtraClass" => "Text", // from CustomSettings
		"RightTitle" => "Varchar(255)", // from CustomSettings
		"ShowOnLoad" => "Boolean(1)", // from CustomSettings
	);

	/**
	 * @config
	 * @var array
	 */
	private static $has_one = array(
		"Parent" => "UserDefinedForm",
	);

	/**
	 * Built in extensions required
	 *
	 * @config
	 * @var array
	 */
	private static $extensions = array(
		"Versioned('Stage', 'Live')"
	);

	/**
	 * @config
	 * @var array
	 */
	private static $has_many = array(
		"DisplayRules" => "EditableCustomRule.Parent" // from CustomRules
	);

	/**
	 * @var bool
	 */
	protected $readonly;

	/**
	 * Set the visibility of an individual form field
	 *
	 * @param bool
	 */ 
	public function setReadonly($readonly = true) {
		$this->readonly = $readonly;
	}

	/**
	 * Returns whether this field is readonly 
	 * 
	 * @return bool
	 */
	private function isReadonly() {
		return $this->readonly;
	}

	/**
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = new FieldList(new TabSet('Root'));

		// Main tab
		$fields->addFieldsToTab(
			'Root.Main',
			array(
				ReadonlyField::create(
					'Type',
					_t('EditableFormField.TYPE', 'Type'),
					$this->i18n_singular_name()
				),
				LiteralField::create(
					'MergeField',
					_t(
						'EditableFormField.MERGEFIELDNAME',
						'<div class="field readonly">' .
							'<label class="left">Merge field</label>' .
							'<div class="middleColumn">' .
								'<span class="readonly">$' . $this->Name . '</span>' .
							'</div>' .
						'</div>'
					)
				),
				TextField::create('Title'),
				TextField::create('Default', _t('EditableFormField.DEFAULT', 'Default value')),
				TextField::create('RightTitle', _t('EditableFormField.RIGHTTITLE', 'Right title'))
			)
		);

		// Custom settings
		if (!empty(self::$allowed_css)) {
			$cssList = array();
			foreach(self::$allowed_css as $k => $v) {
				if (!is_array($v)) {
					$cssList[$k]=$v;
				} elseif ($k === $this->ClassName) {
					$cssList = array_merge($cssList, $v);
				}
			}

			$fields->addFieldToTab('Root.Main',
				DropdownField::create(
					'ExtraClass',
					_t('EditableFormField.EXTRACLASS_TITLE', 'Extra Styling/Layout'),
					$cssList
				)->setDescription(_t(
					'EditableFormField.EXTRACLASS_SELECT',
					'Select from the list of allowed styles'
				))
			);
		} else {
			$fields->addFieldToTab('Root.Main',
				TextField::create(
					'ExtraClass',
					_t('EditableFormField.EXTRACLASS_Title', 'Extra CSS Classes')
				)->setDescription(_t(
					'EditableFormField.EXTRACLASS_MULTIPLE',
					'Separate each CSS class with a single space'
				))
			);
		}

		// Validation
		$fields->addFieldsToTab(
			'Root.Validation',
			$this->getFieldValidationOptions()
		);

		$editableColumns = new GridFieldEditableColumns();
		$editableColumns->setDisplayFields(array(
			'Display' => '',
			'ConditionFieldID' => function($record, $column, $grid) {
				return DropdownField::create(
					$column,
					'',
					EditableFormField::get()
						->filter(array(
							'ParentID' => $this->ParentID
						))
						->exclude(array(
							'ID' => $this->ID
						))
						->map('ID', 'Title')
					);
			},
			'ConditionOption' => function($record, $column, $grid) {
				$options = Config::inst()->get('EditableCustomRule', 'condition_options');
				return DropdownField::create($column, '', $options);
			},
			'FieldValue' => function($record, $column, $grid) {
				return TextField::create($column);
			},
			'ParentID' => function($record, $column, $grid) {
				return HiddenField::create($column, '', $this->ID);
			}
		));

		// Custom rules
		$customRulesConfig = GridFieldConfig::create()
			->addComponents(
				$editableColumns,
				new GridFieldButtonRow(),
				new GridFieldToolbarHeader(),
				new GridFieldAddNewInlineButton(),
				new GridFieldDeleteAction(),
				new GridState_Component()
			);

		$fields->addFieldsToTab('Root.DisplayRules', array(
			CheckboxField::create('ShowOnLoad')
				->setDescription(_t(
					'EditableFormField.SHOWONLOAD',
					'Initial visibility before processing these rules'
				)),
			GridField::create(
				'DisplayRules',
				_t('EditableFormField.CUSTOMRULES', 'Custom Rules'),
				$this->DisplayRules(),
				$customRulesConfig
			)
		));

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}

	/**
	 * @return void
	 */
	public function onBeforeWrite() {
		parent::onBeforeWrite();

		if(!$this->Sort && $this->ParentID) {
			$parentID = $this->ParentID;
			$this->Sort = EditableFormField::get()
				->filter('ParentID', $parentID)
				->max('Sort') + 1;
		}
	}

	/**
	 * @return void
	 */
	public function onAfterWrite() {
		parent::onAfterWrite();

		// Set a field name.
		if(!$this->Name) {
			$this->Name = $this->RecordClassName . $this->ID;
			$this->write();
		}
	}
	
	/**
	 * Flag indicating that this field will set its own error message via data-msg='' attributes
	 * 
	 * @return bool
	 */
	public function getSetsOwnError() {
		return false;
	}
	
	/**
	 * Return whether a user can delete this form field
	 * based on whether they can edit the page
	 *
	 * @return bool
	 */
	public function canDelete($member = null) {
		if($this->Parent()) {
			return $this->Parent()->canEdit($member) && !$this->isReadonly();
		}

		return true;
	}
	
	/**
	 * Return whether a user can edit this form field
	 * based on whether they can edit the page
	 *
	 * @return bool
	 */
	public function canEdit($member = null) {
		if($this->Parent()) {
			return $this->Parent()->canEdit($member) && !$this->isReadonly();
		}

		return true;
	}
	
	/**
	 * Publish this Form Field to the live site
	 * 
	 * Wrapper for the {@link Versioned} publish function
	 */
	public function doPublish($fromStage, $toStage, $createNewVersion = false) {
		$this->publish($fromStage, $toStage, $createNewVersion);

		// Don't forget to publish the related custom rules...
		foreach ($this->DisplayRules() as $rule) {
			$rule->doPublish($fromStage, $toStage, $createNewVersion);
		}
	}
	
	/**
	 * Delete this form from a given stage
	 *
	 * Wrapper for the {@link Versioned} deleteFromStage function
	 */
	public function doDeleteFromStage($stage) {
		$this->deleteFromStage($stage);

		// Don't forget to delete the related custom rules...
		foreach ($this->DisplayRules() as $rule) {
			$rule->deleteFromStage($stage);
		}
	}
	
	/**
	 * checks wether record is new, copied from Sitetree
	 */
	function isNew() {
		if(empty($this->ID)) return true;

		if(is_numeric($this->ID)) return false;

		return stripos($this->ID, 'new') === 0;
	}

	/**
	 * checks if records is changed on stage
	 * @return boolean
	 */
	public function getIsModifiedOnStage() {
		// new unsaved fields could be never be published
		if($this->isNew()) return false;

		$stageVersion = Versioned::get_versionnumber_by_stage('EditableFormField', 'Stage', $this->ID);
		$liveVersion = Versioned::get_versionnumber_by_stage('EditableFormField', 'Live', $this->ID);

		return ($stageVersion && $stageVersion != $liveVersion);
	}
	
	/**
	 * @deprecated since version 4.0
	 */
	public function getSettings() {
		Deprecation::notice('4.0', 'getSettings is deprecated');
		return (!empty($this->CustomSettings)) ? unserialize($this->CustomSettings) : array();
	}
	
	/**
	 * @deprecated since version 4.0
	 */
	public function setSettings($settings = array()) {
		Deprecation::notice('4.0', 'setSettings is deprecated');
		$this->CustomSettings = serialize($settings);
	}
	
	/**
	 * @deprecated since version 4.0
	 */
	public function setSetting($key, $value) {
		Deprecation::notice('4.0', "setSetting({$key}) is deprecated");
		$settings = $this->getSettings();
		$settings[$key] = $value;
		
		$this->setSettings($settings);
	}

	/**
	 * Set the allowed css classes for the extraClass custom setting
	 * 
	 * @param array The permissible CSS classes to add
	 */
	public function setAllowedCss(array $allowed) {
		if (is_array($allowed)) {
			foreach ($allowed as $k => $v) {
				self::$allowed_css[$k] = (!is_null($v)) ? $v : $k;
			}
		}
	}

	/**
	 * @deprecated since version 4.0
	 */
	public function getSetting($setting) {
		Deprecation::notice("4.0", "getSetting({$setting}) is deprecated");

		$settings = $this->getSettings();
		if(isset($settings) && count($settings) > 0) {
			if(isset($settings[$setting])) {
				return $settings[$setting];
			}
		}
		return '';
	}
	
	/**
	 * Get the path to the icon for this field type, relative to the site root.
	 *
	 * @return string
	 */
	public function getIcon() {
		return USERFORMS_DIR . '/images/' . strtolower($this->class) . '.png';
	}
	
	/**
	 * Return whether or not this field has addable options
	 * such as a dropdown field or radio set
	 *
	 * @return bool
	 */
	public function getHasAddableOptions() {
		return false;
	}
	
	/**
	 * Return whether or not this field needs to show the extra
	 * options dropdown list
	 * 
	 * @return bool
	 */
	public function showExtraOptions() {
		return true;
	}

	/**
	 * Title field of the field in the backend of the page
	 *
	 * @return TextField
	 */
	public function TitleField() {
		$label = _t('EditableFormField.ENTERQUESTION', 'Enter Question');
		
		$field = new TextField('Title', $label, $this->getField('Title'));
		$field->setName($this->getFieldName('Title'));

		if(!$this->canEdit()) {
			return $field->performReadonlyTransformation();
		}

		return $field;
	}

	/**
	 * Returns the Title for rendering in the front-end (with XML values escaped)
	 *
	 * @return string
	 */
	public function getTitle() {
		return Convert::raw2att($this->getField('Title'));
	}

	/**
	 * @deprecated since version 4.0
	 */
	public function getFieldName($field = false) {
		Deprecation::notice('4.0', "getFieldName({$field}) is deprecated");
		return ($field) ? "Fields[".$this->ID."][".$field."]" : "Fields[".$this->ID."]";
	}
	
	/**
	 * @deprecated since version 4.0
	 */
	public function getSettingName($field) {
		Deprecation::notice('4.0', "getSettingName({$field}) is deprecated");
		$name = $this->getFieldName('CustomSettings');
		
		return $name . '[' . $field .']';
	}
	
	/**
	 * Append custom validation fields to the default 'Validation' 
	 * section in the editable options view
	 * 
	 * @return FieldList
	 */
	public function getFieldValidationOptions() {
		$fields = new FieldList(
			CheckboxField::create('Required', _t('EditableFormField.REQUIRED', 'Is this field Required?')),
			TextField::create('CustomErrorMessage', _t('EditableFormField.CUSTOMERROR','Custom Error Message'))
		);

        $this->extend('updateFieldValidationOptions', $fields);
		
		return $fields;
	}
	
	/**
	 * Return a FormField to appear on the front end. Implement on 
	 * your subclass
	 *
	 * @return FormField
	 */
	public function getFormField() {
		user_error("Please implement a getFormField() on your EditableFormClass ". $this->ClassName, E_USER_ERROR);
	}
	
	/**
	 * Return the instance of the submission field class
	 *
	 * @return SubmittedFormField
	 */
	public function getSubmittedFormField() {
		return new SubmittedFormField();
	}
	
	
	/**
	 * Show this form field (and its related value) in the reports and in emails.
	 *
	 * @return bool
	 */
	public function showInReports() {
		return true;
	}
 
	/**
	 * Return the validation information related to this field. This is 
	 * interrupted as a JSON object for validate plugin and used in the 
	 * PHP. 
	 *
	 * @see http://docs.jquery.com/Plugins/Validation/Methods
	 * @return Array
	 */
	public function getValidation() {
		return $this->Required
			? array('required' => true)
			: array();
	}
	
	public function getValidationJSON() {
		return Convert::raw2json($this->getValidation());
	}
	
	/**
	 * Return the error message for this field. Either uses the custom
	 * one (if provided) or the default SilverStripe message
	 *
	 * @return Varchar
	 */
	public function getErrorMessage() {
		$title = strip_tags("'". ($this->Title ? $this->Title : $this->Name) . "'");
		$standard = sprintf(_t('Form.FIELDISREQUIRED', '%s is required').'.', $title);
		
		// only use CustomErrorMessage if it has a non empty value
		$errorMessage = (!empty($this->CustomErrorMessage)) ? $this->CustomErrorMessage : $standard;
		
		return DBField::create_field('Varchar', $errorMessage);
	}

	/**
	 * Validate the field taking into account its custom rules.
	 *
	 * @param Array $data
	 * @param UserForm $form
	 *
	 * @return boolean
	 */
	public function validateField($data, $form) {
		if($this->Required && $this->DisplayRules()->Count() == 0) {
			$formField = $this->getFormField();

			if(isset($data[$this->Name])) {
				$formField->setValue($data[$this->Name]);
			}

			if(
				!isset($data[$this->Name]) || 
				!$data[$this->Name] ||
				!$formField->validate($form->getValidator())
			) {
				$form->addErrorMessage($this->Name, $this->getErrorMessage(), 'bad');
			}
		}

		return true;
	}

	/**
	 * Invoked by UserFormUpgradeService to migrate settings specific to this field from CustomSettings
	 * to the field proper
	 *
	 * @param array $data Unserialised data
	 */
	public function migrateSettings($data) {
		// Map 'Show' / 'Hide' to boolean
		if(isset($data['ShowOnLoad'])) {
			$this->ShowOnLoad = $data['ShowOnLoad'] === '' || ($data['ShowOnLoad'] && $data['ShowOnLoad'] !== 'Hide');
			unset($data['ShowOnLoad']);
		}
		
		// Migrate all other settings
		foreach($data as $key => $value) {
			if($this->hasField($key)) {
				$this->setField($key, $value);
			}
		}
	}
}
