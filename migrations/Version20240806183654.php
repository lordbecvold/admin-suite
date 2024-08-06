<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240806183654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ban_list (id INT AUTO_INCREMENT NOT NULL, reason VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, time DATETIME NOT NULL, banned_by_id INT NOT NULL, banned_user_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE logs (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, time DATETIME NOT NULL, user_agent VARCHAR(255) NOT NULL, ip_adderss VARCHAR(255) NOT NULL, level INT NOT NULL, user_id INT NOT NULL, status VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE monitoring (id INT AUTO_INCREMENT NOT NULL, service_name VARCHAR(255) NOT NULL, message VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, last_update_time DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE todos (id INT AUTO_INCREMENT NOT NULL, todo_text LONGTEXT NOT NULL, added_time DATETIME NOT NULL, completed_time DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, user_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(255) NOT NULL, ip_address VARCHAR(255) NOT NULL, user_agent VARCHAR(255) NOT NULL, register_time DATETIME NOT NULL, last_login_time DATETIME NOT NULL, token VARCHAR(255) NOT NULL, profile_pic LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ban_list');
        $this->addSql('DROP TABLE logs');
        $this->addSql('DROP TABLE monitoring');
        $this->addSql('DROP TABLE todos');
        $this->addSql('DROP TABLE users');
    }
}
