# Architecture 1.23.0 received — 2026-09-21

Source commit: 298d014d. Global ADR-091 now records the owner decision formerly
pending in PROJECT/STATUS. The current received snapshot is 1.23.0; scaffold
entries referring to 1.22.1 are historical. No PHP implementation exists yet.
See spec/24_wizard_yii_bootstrap.md. First close the Yii3 startup gate, then
implement this library and integrate its typed Silesco executor.
