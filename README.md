### PRISM ID Generator REDCap External Module
The purpose of this module is to generate PRISM Participant IDs.

The module is triggered when any record in the project with the following conditions is saved:
* The record's [test] and [patient_status_3] values are non-empty
* The record's [unique_id_2] value is empty

The module will look at all other existing PRISM Participant IDs in the project to determine the correct ID for the record being saved. The module will then set the [unique_id_2] field of the saved record and log success or failure (visible in a project's Logging page).

The module builds PRISM IDs by concatenating the site and sequential numeric components, followed conditionally by a non-consenting suffix "-NC".

The site component is the same as a record's [test] value.
The numeric component starts at '001' and increases sequentially. However, non-consenting and consenting patients belong to separate PRISM ID sets. For a given site component '1234', there could exist valid PRISM IDs '1234-001' and '1234-001-NC'. The sequential numeric component is individual to sites so that '1235-001' might also exist and would be a valid PRISM ID in the same project.

Below is an example of what you would find in the Logging page after the 2nd non-consenting patient for site '123' is screened:
![2nd non-consenting patient ID generated](/docs/log_example_1.PNG)