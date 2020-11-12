<?= "<?php\n" ?>
/**
 * This is a generated wrapper class for JIRA custom field '<?= $field_name ?>'
 */
<?php
if (!empty($namespace)) {
    echo "\nnamespace {$namespace};\n";
}
?>

class <?= $class_name ?> extends \Mekras\Jira\CustomFields\SingleUserField
{
    const ID   = '<?= $field_id ?>';
    const NAME = '<?= $field_name ?>';
}
