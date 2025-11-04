# 🚀 WC SGTM Webhook Pro

![Version](https://img.shields.io/badge/version-3.0.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-7.0%2B-purple.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v3-green.svg)

Plugin profissional para integrar **WooCommerce** com **Server-Side Google Tag Manager (SGTM)** via Data Client, otimizado para **Meta Conversions API** com alta **Event Match Quality (EMQ)**.

---

## ✨ Funcionalidades

- ✅ **Envio automático** de eventos `purchase` para SGTM
- ✅ **Suporte completo** a Stape.io e Self-Hosted SGTM
- ✅ **EMQ ≥ 8/10** - Event Match Quality otimizado
- ✅ **Hash SHA-256** automático de dados pessoais (LGPD compliant)
- ✅ **Dashboard administrativo** com estatísticas em tempo real
- ✅ **Sistema de logs** com rotação automática
- ✅ **Reenvio manual** de webhooks
- ✅ **Teste de conexão** integrado
- ✅ **Bearer Token** para autenticação opcional

---

## 📊 Dados Enviados (Match Keys)

### Alta Prioridade
- `em` (email) - hasheado + plain
- `ph` (phone) - hasheado + plain
- `fn` (first name) - hasheado + plain
- `ln` (last name) - hasheado + plain

### Média/Baixa Prioridade
- `ct` (city)
- `st` (state)
- `zp` (zip code)
- `country` (country code)
- `external_id` (user ID)
- `fbp` / `fbc` (se disponíveis)

### Custom Data
- Valor total, moeda, ID do pedido
- Itens do pedido (nome, quantidade, preço)
- Categorias, marcas, SKUs
- Subtotal, impostos, frete, descontos
- Cupons aplicados

---

## 📦 Instalação

### Via WordPress Admin

1. Baixe o arquivo `wc-sgtm-webhook.zip`
2. Vá para **Plugins > Adicionar Novo > Enviar Plugin**
3. Selecione o arquivo ZIP
4. Clique em **Instalar Agora** e depois **Ativar**

### Via FTP

1. Extraia o arquivo ZIP
2. Envie a pasta `wc-sgtm-webhook` para `/wp-content/plugins/`
3. Ative o plugin em **Plugins > Plugins Instalados**

### Via WP-CLI

```bash
wp plugin install wc-sgtm-webhook.zip --activate
```

---

## ⚙️ Configuração

### 1. Acesse as Configurações

Vá para **WooCommerce > SGTM Webhook**

### 2. Preencha os Campos

Na aba **Configurações**:

| Campo | Descrição | Exemplo |
|-------|-----------|---------|
| **URL do Webhook** | URL base do servidor SGTM | `https://sgtm.seudominio.com` |
| **Container ID** | ID do container GTM Server-Side | `GTM-XXXXXXX` |
| **Token** (opcional) | Bearer token para autenticação | `seu_token_secreto` |
| **Ativar Webhook** | Checkbox para ativar envio | ✅ Marcado |
| **Modo Debug** | Ativar logs detalhados | ⚠️ Apenas para testes |

### 3. Teste a Conexão

1. Vá para a aba **Ferramentas**
2. Clique em **🧪 Testar Conexão**
3. Verifique se retorna **HTTP 200** ou **405** (ambos são OK)

### 4. Faça um Pedido de Teste

1. Faça um pedido de teste no WooCommerce
2. Vá para a aba **Pedidos**
3. Verifique se o webhook foi enviado com sucesso (✅)
4. Confirme no **SGTM Debug Mode** se o evento chegou

---

## 🔧 Endpoint Gerado

O plugin constrói automaticamente o endpoint final:

```
https://sgtm.seudominio.com/data?id=GTM-XXXXXXX
```

- **Base URL**: configurada por você
- **/data**: adicionado automaticamente
- **?id=GTM-XXX**: construído com o Container ID

---

## 📖 Uso

### Dashboard

Visualize estatísticas em tempo real:
- Status do webhook (ativo/inativo)
- Envios de hoje
- Total de envios
- Modo debug
- Último pedido enviado

### Pedidos

Lista dos últimos 20 pedidos com:
- Data e status do pedido
- Status do webhook (✅ Enviado / ❌ Erro / ⏳ Pendente)
- Código de resposta HTTP
- Botão **🔄 Reenviar** para tentar novamente

### Ferramentas

- **🧪 Testar Conexão**: Envia um evento de teste
- **🗑️ Limpar Logs**: Remove logs antigos
- **📋 Ver Todos os Logs**: Acessa logs do WooCommerce
- **📝 Logs Recentes**: Visualiza últimos 15 logs

---

## 🔒 Segurança & Privacidade

### LGPD Compliant

✅ Todos os dados pessoais são hasheados com **SHA-256** antes do envio:
- Email, telefone, nome, sobrenome
- Cidade, estado, CEP, país
- User ID

### Transmissão Segura

✅ Comunicação via **HTTPS** com SSL verify habilitado
✅ Suporte a **Bearer Token** para autenticação
✅ Validação de nonce em todas as ações admin
✅ Sanitização de todos os inputs

---

## 🐛 Troubleshooting

### Webhook não dispara

1. Verifique se o webhook está **ativo** (checkbox marcado)
2. Confirme se a URL está correta
3. Teste a conexão na aba **Ferramentas**

### Erro 404

- A URL está incorreta ou o path `/data` não existe
- Verifique se o servidor SGTM está rodando

### Erro SSL

- O certificado SSL do WordPress ou do SGTM está inválido
- Temporariamente desabilite SSL verify (não recomendado)

### Dados não chegam no Facebook

1. Verifique se a tag `Purchase` está configurada no SGTM
2. Confirme se o **Data Client** está recebendo os eventos
3. Verifique o **Event Match Quality** no Meta Events Manager
4. Confirme se o Pixel ID e Access Token estão corretos

---

## 📚 Estrutura de Arquivos

```
wc-sgtm-webhook/
├── wc-sgtm-webhook.php          # Arquivo principal
├── includes/
│   ├── class-helpers.php         # Funções auxiliares
│   ├── class-core.php            # Lógica de envio
│   ├── class-admin.php           # Interface admin
│   └── class-ajax.php            # Handlers AJAX
├── assets/
│   ├── css/
│   │   └── admin.css             # Estilos admin
│   └── js/
│       └── admin.js              # Scripts admin
├── languages/                    # Traduções (futuro)
├── readme.txt                    # README WordPress.org
├── CHANGELOG.md                  # Changelog
└── README.md                     # Este arquivo
```

---

## 🛣️ Roadmap

### v3.1.0 (Q1 2025)
- [ ] Suporte a HPOS (High-Performance Order Storage)
- [ ] Eventos adicionais (ViewContent, AddToCart, InitiateCheckout)
- [ ] Integração com ActionScheduler
- [ ] Webhook personalizado por gateway

### v3.2.0 (Q2 2025)
- [ ] Suporte a múltiplos endpoints
- [ ] Campos customizados configuráveis
- [ ] Exportação de relatórios em PDF
- [ ] Integração com Google Analytics 4

---

## 🤝 Contribuindo

Contribuições são bem-vindas! Para contribuir:

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/MinhaFeature`)
3. Commit suas mudanças (`git commit -m 'Adiciona MinhaFeature'`)
4. Push para a branch (`git push origin feature/MinhaFeature`)
5. Abra um Pull Request

---

## 📄 Licença

Este projeto está licenciado sob a **GPL v3** - veja o arquivo [LICENSE](LICENSE) para detalhes.

---

## 👨‍💻 Autor

**Carlos Araújo** - [Alta Cúpula / Elevelife](https://elevelife.com)

---

## 🙏 Agradecimentos

- Equipe **Stape.io** pela infraestrutura SGTM
- Comunidade **WooCommerce**
- **Meta Developer Documentation**

---

## 📧 Suporte

- Email: suporte@elevelife.com
- GitHub Issues: [Criar issue](https://github.com/elevelife/wc-sgtm-webhook/issues)
- Documentação: https://docs.elevelife.com/wc-sgtm-webhook

---

## ⭐ Se gostou, dê uma estrela!

Se este plugin foi útil para você, considere dar uma ⭐ no GitHub!

---

**Desenvolvido com ❤️ para a comunidade WooCommerce**
