<?php
if(version_compare(phpversion(), '7.1', '>=')) {
	require_once DIR_SYSTEM . 'library/dream_filter/admin_model_7.1.php';
} else {
	require_once DIR_SYSTEM . 'library/dream_filter/admin_model.php';
}