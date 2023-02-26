<?php


use Phinx\Migration\AbstractMigration;

class AddAdditionalNoteFieldsToItems extends AbstractMigration {
    public function change() {
        $this->table('items')
            ->addColumn('requirement', 'string', ['null' => true])
            ->addColumn('warning', 'string')
            ->addColumn('information', 'string', ['null' => true])
            ->update();
    }
}
