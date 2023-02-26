<?php


use Phinx\Migration\AbstractMigration;

class AddOrderToDisguises extends AbstractMigration {
    public function change() {
        $this->table('disguises')
            ->addColumn('order', 'integer', ['null' => true])
            ->addColumn('description', 'text', ['limit' => 'text_medium', 'null' => true])
            ->update();
    }
}
