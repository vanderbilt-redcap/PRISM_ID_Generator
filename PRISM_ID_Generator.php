<?php
namespace Vanderbilt\PRISM_ID_Generator;
class PRISM_ID_Generator extends \ExternalModules\AbstractExternalModule {

	function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
		// the purpose of this hook (and module at large) is to generate correct PRISM Participant IDs for patients (stored in field [unique_id_2])
		// A PRISM Participant ID looks like '1234-001-NC' or '1235-002'
		// Where the first four digits come from a record's [test] value (previously determined). This is the site number for the site the patient belongs to.
		// The second part of the id '001', '002', and so on, is the sequential, 3 digit, padded, numeric component of the PRISM Participant ID
		// Patients who do not consent ([patient_status_2] == '3') have a '-NC' appended to their PRISM Participant ID
		
		$verbose = $this->getProjectSetting('verbose_logging');
		if (empty($record) or empty($project_id)) {
			if ($verbose) {
				\REDCap::logEvent("PRISM ID Generator Module", "Didn't generate PRISM Participant ID upon save record (empty record or project_id variables)");
			}
			return;
		}
		
		/* get this record's values for these fields:
			[test]
			[patient_status_2]
			[unique_id_2]
		*/
		$rid_field = $this->getRecordIDField($project_id);
		$record_data = json_decode(\REDCap::getData($project_id, 'json', $record, [
			$rid_field,
			'test',
			'patient_status_2',
			'unique_id_2'
		]));
		if (empty($record_data)) {
			if ($verbose) {
				\REDCap::logEvent("PRISM ID Generator Module", "Didn't generate PRISM Participant ID upon save record (empty record_data variable)");
			}
			return;
		}
		
		// pull object out of array
		$init_record_data = $record_data;
		$record_data = $record_data[0];
		
		// ensure precursors all exist
		$precursor_fields = [
			'test',
			'patient_status_2'
		];
		foreach ($precursor_fields as $field_name) {
			if (empty($record_data->$field_name) and $record_data->$field_name !== 0 and $record_data->$field_name !== '0') {
				// empty $field_name, aborting PRISM ID generation
				if ($verbose) {
					\REDCap::logEvent("PRISM ID Generator Module", "Didn't generate PRISM Participant ID upon save record (empty $field_name variable). Record data (for debugging): " . print_r($init_record_data, true));
				}
				return;
			}
		}
		
		// ensure this record doesn't already have a PRISM Participant ID ([unique_id_2])
		if (!empty($record_data->unique_id_2)) {
			// record has a non-empty [unique_id_2], aborting PRISM ID generation
			if ($verbose) {
				\REDCap::logEvent("PRISM ID Generator Module", "Didn't generate PRISM Participant ID upon save record (non-empty unique_id_2 variable)");
			}
			return;
		}
		
		// Record saved with non-empty [test] and [patient_status_2] fields and an empty [unique_id_2] field. Generating PRISM Participant ID now.
		
		// get all existing PRISM Participant IDs
		$prism_id_data = json_decode(\REDCap::getData($project_id, 'json', NULL, [
			$rid_field,
			'unique_id_2'
		]));
		
		// iterate over prism_id_data and find the next number in sequence that we should use to build this record's PRISM Participant ID
		$last_id_number = 0;
		// sequential numeric component advances separately for consenting and non-consenting patients
		// such that '1234-001-NC' and '1234-001' are both valid PRISM ids
		if ($record_data->patient_status_2 == '3') {
			$saved_record_consented = false;
		} else {
			$saved_record_consented = true;
		}
		foreach ($prism_id_data as $record) {
			if (!empty($record->unique_id_2)) {
				$id_pieces = explode('-', $record->unique_id_2);
				$site_part_of_id = $id_pieces[0];
				$number_part_of_id = $id_pieces[1];
				
				// determine if the PRISM id is from a consenting patient
				if (strpos($record->unique_id_2, '-NC') === false) {
					$this_record_consented = true;
				} else {
					$this_record_consented = false;
				}
				
				if ($site_part_of_id == $record_data->test and $this_record_consented === $saved_record_consented) {
					// found another prism id with site number that matches ours ($site_part_of_id) and consent statuses match
					$last_id_number = max($last_id_number, intval($number_part_of_id));
				}
			}
		}
		
		// create numeric component of the PRISM id using the highest integer found in other PRISM IDs of this set ($last_id_number)
		$numeric_precursor = $last_id_number + 1;
		if ($numeric_precursor > 999) {
			throw new \Exception("Numeric part of PRISM Participant ID calculated to be higher than 999 -- not allowed");
		}
		$numeric_id_component = str_pad(strval($numeric_precursor), 3, '0', STR_PAD_LEFT);
		
		$prism_participant_id = $record_data->test . '-' . $numeric_id_component;
		if (!$saved_record_consented) {
			// append '-NC' if patient non-consenting
			$prism_participant_id = $prism_participant_id . '-NC';
		}
		
		$record_data->unique_id_2 = $prism_participant_id;
		$data_to_be_saved = json_encode([$record_data]);
		$results = \REDCap::saveData($project_id, 'json', $data_to_be_saved);
		if (empty($results['errors'])) {
			\REDCap::logEvent("PRISM ID Generator Module", "Generated PRISM Participant ID ([unique_id_2]) value of $prism_participant_id for record with $rid_field " . $record_data->$rid_field);
		} else {
			\REDCap::logEvent("PRISM ID Generator Module", "Encountered errors trying to saved generated PRISM Participant ID for record with $rid_field " . print_r($results['errors'], true));
		}
	}
}