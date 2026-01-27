<?php
// Backwards-compat: this page now simply forwards to Claim Account in "parent" mode.
header('Location: claim.php?role=parent');
exit;
