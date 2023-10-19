<?php namespace ProcessWire;

class ProcessCloneComboSubfield extends Process {

	/**
	 * Execute
	 */
	public function ___execute() {
		$modules = $this->wire()->modules;
		$input = $this->wire()->input;
		$fields = $this->wire()->fields;
		$session = $this->wire()->session;

		$combo_fields = $fields->find("type=FieldtypeCombo");

		$field_id = (int) $input->get('field');
		if($field_id) {
			/** @var ComboField $combo */
			$combo = $combo_fields->get("id=$field_id");
			if(!$combo) return $this->_("Combo field not found.");

			$url = $input->url(true);
			$settings = $combo->getComboSettings();
			$subfields = $settings->getSubfields();
			$this->headline($this->wire()->page->title . ": $combo->name");

			if(!$subfields) return $this->_("This Combo field does not contain any subfields.");

			// Form
			/** @var InputfieldForm $form */
			$form = $modules->get('InputfieldForm');
			$form->action = $url;

			// Select subfield
			/** @var InputfieldSelect $f */
			$f = $modules->get('InputfieldSelect');
			$f->name = 'subfield';
			$f->label = $this->_('Combo subfield to clone');
			foreach(array_keys($subfields) as $name) {
				$f->addOption($name);
			}
			$f->required = true;
			$form->add($f);

			// New subfields to create
			/** @var InputfieldTextarea $f */
			$f = $modules->get('InputfieldTextarea');
			$f->name = 'newSubfields';
			$f->label = $this->_('Names and labels of new subfields');
			$f->description = $this->_('List the new subfields you want to create, one per line, in the format: name|label. Example: my_new_subfield|My new subfield');
			$f->required = true;
			$form->add($f);

			// Submit button
			$form->add($modules->get('InputfieldSubmit'));

			// If form was submitted
			if($form->isSubmitted()) {

				// Process input so any error messages are shown
				$form->processInput($input->post);

				// If required values are present
				if($input->post('subfield') && $input->post('newSubfields')) {
					$subfield_name = $input->post('subfield');
					$subfield = $subfields[$subfield_name] ?? null;

					$lines = explode("\n", str_replace("\r", "", $input->post('newSubfields')));
					$create_subfields = [];
					foreach($lines as $line) {
						$pieces = explode('|', $line);
						// Must be two pieces in the line
						if(count($pieces) !== 2) continue;
						list($name, $label) = $pieces;
						// Name must not match an existing subfield
						if(isset($subfields[$name])) continue;
						$create_subfields[$name] = $label;
					}

					if($subfield && $create_subfields) {
						$last_num = $settings->findMaxQty();
						$export_data = $combo->getExportData();
						$prefix = "i{$subfield['num']}_";
						$prefix_length = strlen($prefix);
						$source_settings = [];
						foreach($export_data as $key => $value) {
							if(strpos($key, $prefix) !== 0) continue;
							$key = substr($key, $prefix_length);
							$source_settings[$key] = $value;
						}

						$import_data = $export_data;
						$i = 1;
						foreach($create_subfields as $name => $label) {
							$num = $last_num + $i;
							$new_prefix = "i{$num}_";
							foreach($source_settings as $key => $value) {
								if($key === 'name') $value = $name;
								if($key === 'label') $value = $label;
								$import_data[$new_prefix . $key] = $value;
							}
							++$i;
						}

						$combo->setImportData($import_data);
						$combo->save();
					}

					// Success message
					$count = count($create_subfields);
					$session->message(sprintf($this->_('Created %d new subfield(s)'), $count));

					// Redirect back to the current URL
					$session->location($url);
				}
			}

			return $form->render();
		}

		// No Combo field specified so output a link for each Combo field
		else {
			$out = '';
			if($combo_fields->count) {
				$out .= "<h3>" . $this->_("Select a Combo field") . "</h3>";
				$out .= "<ul>";
				foreach($combo_fields as $field) {
					$out .= "<li><a href='./?field=$field->id'>$field->name</a></li>";
				}
				$out .= "</ul>";
			} else {
				$out .= $this->_("This site does not have any Combo fields.");
			}
			return $out;
		}
	}

}
