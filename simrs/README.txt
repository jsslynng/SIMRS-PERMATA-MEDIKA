SIMRS — FINAL REVISED DEMO BUILD

INSTALL / RUN
1. Extract the ZIP. Copy the inner folder `simrs_updated` into /Applications/XAMPP/htdocs/.
2. Keep the folder name consistent with your URL. Example: /Applications/XAMPP/htdocs/simrs_revised/ should contain index PHP files directly, not another nested project folder.
3. Start Apache and MySQL in XAMPP.
4. Confirm config/database.php points to db_simrs_integrated (default: 127.0.0.1, root, blank MySQL password).
5. Use the existing database and back it up before testing. Do NOT re-import db_simrs_integrated.sql over a populated database.
6. Open http://localhost/simrs_revised/login_baru.php (or the folder name you chose).

PAGE WORKFLOWS
- ED / Triage nurse: Triage Worklist and a separate Input Triage & TTV page. Select a patient before entering vitals.
- ED / Doctor: Encounter List and a separate SOAP assessment page for the selected encounter. S, O, A, and P are kept in one integrated note; the latest note is prefilled for editing rather than duplicated. Diagnosis name/code and disposition are handled from the assessment page.
- Scheduling / Registration: Appointment List, Registrasi Pasien Baru, and Buat Appointment are separate pages linked from the sidebar. Existing patients/appointments remain in the same database.
- Radiology / Doctor: Radiology Worklist and a separate order-entry page.
- Radiology / Radiographer: Worklist and a separate verification/scheduling/examination-status page. Patient identity confirmation is required for in-progress/completed states.
- Radiology / Radiologist: Worklist and a dedicated report/interpretation page. Amending a finalized report records the prior text in audit_logs.
- dashboard.php, users.php, reports.php remain the administrative modules.

DEMO PASSWORDS
The optional reset_demo_passwords.php script sets the planned demo passwords for listed demo accounts and does NOT update admin. Run it only if needed, verify its output, then DELETE the script from the web-accessible folder. Demo passwords are not suitable for production.

IMPORTANT LIMITATIONS / TESTING
- Academic/local demo only; use fictional patient data, not real clinical records.
- PHP syntax was checked for all PHP files in this package. This environment cannot run the package against your local XAMPP/MySQL instance, so runtime/database behavior must still be checked on your Mac before submission.
- The radiology page does not include a DICOM/image viewer. It explicitly notes that no viewer is connected; do not present a placeholder as a real patient image.
- ICD-10 diagnosis code is a manual field; a searchable ICD-10 catalogue is not included.
- Back up the database before testing. Do not re-import the complete SQL dump into your existing populated database.
