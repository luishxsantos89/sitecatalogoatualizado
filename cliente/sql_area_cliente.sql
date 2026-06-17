-- ============================================================
-- SiteCatalogo2 - Área do Cliente
-- Execute este SQL uma única vez no banco de dados do projeto
-- (ajustado para o prefixo "sc_" usado em sitecatalogo2)
-- ============================================================

-- Adiciona coluna de senha (hash) na tabela de clientes
ALTER TABLE sc_clientes
    ADD COLUMN senha VARCHAR(255) NULL DEFAULT NULL AFTER email;

-- (Opcional) Coluna para marcar se o cliente já definiu senha / pode logar
ALTER TABLE sc_clientes
    ADD COLUMN acesso_ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER senha;
