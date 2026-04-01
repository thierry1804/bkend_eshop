<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne frequence (défaut 1) sur budget_items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget_items ADD frequence INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE budget_items DROP frequence');
    }
}
