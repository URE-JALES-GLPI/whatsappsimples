<?php

namespace GlpiPlugin\Whatsappsimples\Controller;

use Glpi\Controller\AbstractController;
use Session;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatPageController extends AbstractController
{
    #[Route('/Chat', name: 'whatsappsimples_chat', methods: ['GET'])]
    public function __invoke(): Response
    {
        Session::checkLoginUser();
        Session::checkRight('plugin_whatsappsimples', READ);
        global $CFG_GLPI;

        ob_start();
        include_once GLPI_ROOT . '/inc/includes.php';
        \Html::header('WhatsApp', $_SERVER['PHP_SELF'], 'tools', 'whatsappsimples');

        $canTransfer = Session::haveRight('plugin_whatsappsimples_transfer', READ);
        ?>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

            .omni-app {
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                display: flex;
                flex-direction: column;
                height: calc(100vh - 100px);
                background: #f1f5f9;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
                border: 1px solid #cbd5e1;
                margin-top: -5px;
                position: relative;
            }

            /* TOP NAVBAR OMNICHANNEL */
            .omni-navbar {
                height: 48px;
                background: #1e293b;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 16px;
                color: #ffffff;
                user-select: none;
            }

            .omni-brand {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 1.1rem;
                font-weight: 700;
                color: #ffffff;
                letter-spacing: -0.3px;
            }
            .omni-brand span { color: #38bdf8; }

            .omni-nav-tools {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .omni-nav-icon {
                width: 34px;
                height: 34px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 6px;
                color: #cbd5e1;
                cursor: pointer;
                font-size: 1.1rem;
                transition: all 0.15s;
            }
            .omni-nav-icon:hover { background: rgba(255,255,255,0.1); color: #ffffff; }
            .omni-nav-icon.active {
                background: #0f172a;
                color: #ffffff;
                border: 2px solid #ef4444;
            }

            .omni-user-badge {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                background: #ec4899;
                color: #ffffff;
                font-weight: 700;
                font-size: 0.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-left: 8px;
            }

            /* BODY LAYOUT */
            .omni-body {
                flex: 1;
                display: flex;
                overflow: hidden;
                width: 100%;
                min-width: 0;
            }

            /* SIDEBAR ESQUERDA */
            .omni-sidebar {
                width: 360px;
                min-width: 360px;
                max-width: 360px;
                flex-shrink: 0;
                background: #ffffff;
                border-right: 1px solid #e2e8f0;
                display: flex;
                flex-direction: column;
            }

            .omni-sidebar-header {
                padding: 12px 16px 8px 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 1.05rem;
                font-weight: 700;
                color: #1e293b;
            }

            .omni-search-box {
                padding: 0 16px 8px 16px;
                display: flex;
                gap: 8px;
            }

            .omni-search-input-wrap {
                flex: 1;
                position: relative;
                display: flex;
                align-items: center;
            }

            .omni-search-input-wrap span {
                position: absolute;
                left: 10px;
                color: #94a3b8;
                font-size: 0.9rem;
            }

            .omni-search-input {
                width: 100%;
                padding: 7px 10px 7px 32px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                font-size: 0.85rem;
                outline: none;
                background: #f8fafc;
            }
            .omni-search-input:focus { border-color: #0284c7; background: #ffffff; }

            .omni-filter-btn {
                width: 32px;
                height: 32px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                background: #ffffff;
                color: #64748b;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
            }

            /* ABAS (CHATS, FILA, CONTATOS) */
            .omni-tabs {
                display: flex;
                border-bottom: 1px solid #e2e8f0;
                padding: 0 8px;
            }

            .omni-tab-btn {
                flex: 1;
                padding: 10px 4px;
                border: none;
                background: transparent;
                font-size: 0.85rem;
                font-weight: 600;
                color: #64748b;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                border-bottom: 2px solid transparent;
                transition: all 0.15s;
            }
            .omni-tab-btn.active {
                color: #0284c7;
                border-bottom-color: #0284c7;
            }

            .omni-tab-badge {
                padding: 1px 6px;
                border-radius: 10px;
                font-size: 0.7rem;
                font-weight: 700;
                background: #e2e8f0;
                color: #475569;
            }
            .omni-tab-btn.active .omni-tab-badge {
                background: #0284c7;
                color: #ffffff;
            }
            .omni-tab-badge.alert,
            .omni-tab-btn.active .omni-tab-badge.alert {
                background: #ef4444;
                color: #ffffff;
                animation: omniBadgePulse 1.2s ease-in-out infinite;
            }
            @keyframes omniBadgePulse {
                0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
                50% { transform: scale(1.15); box-shadow: 0 0 0 6px rgba(239,68,68,0); }
            }

            .omni-subsort {
                padding: 6px 16px;
                font-size: 0.75rem;
                color: #64748b;
                background: #f8fafc;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .omni-subsort span { color: #0284c7; font-weight: 600; cursor: pointer; }

            /* LISTA DE CHATS */
            .omni-chat-list {
                flex: 1;
                overflow-y: auto;
            }

            .omni-chat-card {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-bottom: 1px solid #f1f5f9;
                cursor: pointer;
                transition: background 0.15s;
            }
            .omni-chat-card:hover { background: #f8fafc; }
            .omni-chat-card.selected {
                background: #e0f2fe;
                border-left: 4px solid #0284c7;
            }

            .omni-avatar-wrap {
                position: relative;
            }

            .omni-avatar {
                width: 42px;
                height: 42px;
                border-radius: 50%;
                background: #0284c7;
                color: #ffffff;
                font-weight: 700;
                font-size: 1rem;
                display: flex;
                align-items: center;
                justify-content: center;
                text-transform: uppercase;
                overflow: hidden;
                position: relative;
                user-select: none;
            }
            .omni-avatar img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
                display: block;
            }

            .omni-avatar-icon {
                position: absolute;
                bottom: -2px;
                right: -2px;
                width: 18px;
                height: 18px;
                border-radius: 50%;
                background: #22c55e;
                color: #ffffff;
                font-size: 0.65rem;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 2px solid #ffffff;
            }

            .omni-card-info {
                flex: 1;
                min-width: 0;
            }

            .omni-card-row1 {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2px;
            }

            .omni-card-name {
                font-weight: 600;
                font-size: 0.9rem;
                color: #1e293b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .omni-card-time {
                font-size: 0.72rem;
                color: #94a3b8;
            }

            .omni-card-row2 {
                display: flex;
                align-items: center;
                gap: 4px;
                font-size: 0.8rem;
                color: #64748b;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /* MAIN CHAT WINDOW */
            .omni-main {
                flex: 1;
                min-width: 0;
                display: flex;
                flex-direction: column;
                background: #efeae2;
                position: relative;
                overflow: hidden;
            }

            /* CHAT HEADER BAR */
            .omni-chat-header {
                height: 54px;
                background: #ffffff;
                border-bottom: 1px solid #cbd5e1;
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 16px;
                z-index: 10;
                flex-shrink: 0;
                min-width: 0;
            }

            .omni-header-contact {
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 0;
                overflow: hidden;
                cursor: pointer;
                padding: 4px 8px;
                border-radius: 6px;
                transition: background 0.15s;
                user-select: none;
            }
            .omni-header-contact:hover {
                background: #f1f5f9;
            }

            .omni-header-contact > div:last-child {
                min-width: 0;
                overflow: hidden;
            }

            #header-title {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            #header-sub {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                color: #64748b;
                transition: color 0.15s;
            }
            .omni-header-contact:hover #header-sub {
                color: #0284c7;
            }

            /* CONTACT DRAWER (DADOS DO CONTATO - WHATSAPP STYLE) */
            .omni-contact-drawer {
                width: 380px;
                min-width: 380px;
                max-width: 380px;
                background: #ffffff;
                border-left: 1px solid #cbd5e1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                flex-shrink: 0;
                animation: omniDrawerSlideIn 0.2s ease-out;
                z-index: 20;
            }
            @keyframes omniDrawerSlideIn {
                from { transform: translateX(100%); }
                to { transform: translateX(0); }
            }
            .omni-drawer-header {
                height: 54px;
                background: #ffffff;
                border-bottom: 1px solid #cbd5e1;
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 0 16px;
                flex-shrink: 0;
            }
            .omni-drawer-close-btn {
                font-size: 1.3rem;
                font-weight: 700;
                cursor: pointer;
                color: #64748b;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.15s;
            }
            .omni-drawer-close-btn:hover {
                background: #f1f5f9;
                color: #0f172a;
            }
            .omni-drawer-title {
                font-size: 1rem;
                font-weight: 700;
                color: #0f172a;
            }
            .omni-drawer-body {
                flex: 1;
                overflow-y: auto;
                background: #f8fafc;
                display: flex;
                flex-direction: column;
                gap: 10px;
                padding-bottom: 24px;
            }
            .omni-drawer-card {
                background: #ffffff;
                padding: 16px;
                border-bottom: 1px solid #e2e8f0;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }
            .omni-drawer-hero {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding-top: 24px;
                padding-bottom: 20px;
            }
            .omni-drawer-avatar-wrap {
                position: relative;
                width: 160px;
                height: 160px;
                border-radius: 50%;
                cursor: pointer;
                overflow: hidden;
                box-shadow: 0 4px 14px rgba(0,0,0,0.12);
                margin-bottom: 14px;
            }
            .omni-drawer-avatar-wrap img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
                transition: transform 0.2s;
            }
            .omni-drawer-avatar-wrap:hover img {
                transform: scale(1.05);
            }
            .omni-drawer-avatar-fallback {
                width: 100%;
                height: 100%;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #0ea5e9, #0284c7);
                color: #ffffff;
                font-size: 3.5rem;
                font-weight: 700;
                user-select: none;
            }
            .omni-drawer-avatar-overlay {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(0,0,0,0.6);
                color: #ffffff;
                font-size: 0.72rem;
                font-weight: 600;
                padding: 4px 0;
                opacity: 0;
                transition: opacity 0.2s;
                text-align: center;
            }
            .omni-drawer-avatar-wrap:hover .omni-drawer-avatar-overlay {
                opacity: 1;
            }
            .omni-drawer-name {
                font-size: 1.25rem;
                font-weight: 700;
                color: #0f172a;
                word-break: break-word;
            }
            .omni-drawer-phone {
                font-size: 0.9rem;
                color: #64748b;
                margin-top: 4px;
            }
            .omni-drawer-actions {
                display: flex;
                justify-content: center;
                gap: 16px;
                margin-top: 18px;
                width: 100%;
            }
            .omni-drawer-action-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 6px;
                background: none;
                border: none;
                cursor: pointer;
                color: #0284c7;
                font-size: 0.75rem;
                font-weight: 600;
                transition: transform 0.15s;
            }
            .omni-drawer-action-btn:hover {
                transform: translateY(-2px);
            }
            .omni-drawer-action-circle {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: #e0f2fe;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.15rem;
                color: #0284c7;
                transition: all 0.15s;
            }
            .omni-drawer-action-btn:hover .omni-drawer-action-circle {
                background: #0284c7;
                color: #ffffff;
            }
            .omni-drawer-section-title {
                font-size: 0.82rem;
                font-weight: 600;
                color: #64748b;
                margin-bottom: 8px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .omni-drawer-recado-text {
                font-size: 0.92rem;
                color: #1e293b;
                word-break: break-word;
                line-height: 1.4;
            }
            .omni-drawer-recado-date {
                font-size: 0.75rem;
                color: #94a3b8;
                margin-top: 6px;
            }
            .omni-drawer-media-grid {
                display: flex;
                gap: 8px;
                overflow-x: auto;
                padding: 4px 0 8px 0;
            }
            .omni-drawer-media-grid::-webkit-scrollbar {
                height: 6px;
            }
            .omni-drawer-media-grid::-webkit-scrollbar-thumb {
                background: #cbd5e1;
                border-radius: 3px;
            }
            .omni-drawer-media-thumb {
                width: 76px;
                height: 76px;
                min-width: 76px;
                border-radius: 6px;
                overflow: hidden;
                position: relative;
                cursor: pointer;
                background: #e2e8f0;
                border: 1px solid #cbd5e1;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.15s;
            }
            .omni-drawer-media-thumb:hover {
                transform: scale(1.05);
            }
            .omni-drawer-media-thumb img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .omni-header-tags {
                display: flex;
                align-items: center;
                gap: 6px;
                flex-shrink: 0;
            }

            .omni-tag-badge {
                padding: 3px 8px;
                border-radius: 12px;
                font-size: 0.72rem;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .omni-tag-whatsapp { background: #22c55e; color: #ffffff; }
            .omni-tag-dept { background: #64748b; color: #ffffff; }

            .omni-finish-btn {
                padding: 6px 14px;
                background: #ef4444;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-size: 0.82rem;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: background 0.15s;
            }
            .omni-finish-btn:hover { background: #dc2626; }

            .omni-transfer-btn {
                padding: 6px 14px;
                background: #3b82f6;
                color: #ffffff;
                border: none;
                border-radius: 6px;
                font-size: 0.82rem;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 6px;
                transition: background 0.15s;
            }
            .omni-transfer-btn:hover { background: #2563eb; }

            /* MESSAGES AREA */
            .omni-messages-area {
                flex: 1;
                padding: 20px;
                overflow-y: auto;
                overflow-x: hidden;
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 12px;
                background-color: #efeae2;
                background-image: radial-gradient(#cbd5e1 0.75px, transparent 0.75px);
                background-size: 16px 16px;
            }

            .omni-divider-badge {
                align-self: center;
                background: #e2e8f0;
                color: #475569;
                padding: 4px 14px;
                border-radius: 12px;
                font-size: 0.75rem;
                font-weight: 500;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                margin: 8px 0;
            }

            .omni-system-badge {
                align-self: center;
                background: #cbd5e1;
                color: #334155;
                padding: 5px 18px;
                border-radius: 12px;
                font-size: 0.78rem;
                font-weight: 600;
                text-align: center;
                box-shadow: 0 1px 2px rgba(0,0,0,0.08);
                margin: 10px 0;
            }

            .omni-date-badge {
                align-self: center;
                background: #ffffff;
                color: #54656f;
                padding: 4px 14px;
                border-radius: 8px;
                font-size: 0.72rem;
                font-weight: 600;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.08);
                border: 1px solid rgba(0,0,0,0.04);
                margin: 12px 0 6px 0;
                user-select: none;
            }

            .omni-bubble {
                max-width: 75%;
                min-width: 0;
                padding: 8px 12px 6px 12px;
                border-radius: 8px;
                font-size: 0.88rem;
                line-height: 1.45;
                position: relative;
                overflow-wrap: anywhere;
                word-break: break-word;
                word-wrap: break-word;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
                box-sizing: border-box;
            }
            .omni-bubble.user, .omni-bubble.contact {
                align-self: flex-start;
                background: #f1f5f9;
                color: #0f172a;
                border: 1px solid #e2e8f0;
                border-top-left-radius: 0;
            }
            .omni-bubble.attendant {
                align-self: flex-end;
                background: #dcf8c6;
                color: #0f172a;
                border: 1px solid #bce2a4;
                border-top-right-radius: 0;
            }
            .omni-bubble.omni-msg-internal {
                background: #fef08a !important;
                color: #854d0e !important;
                border: 1px solid #fde047 !important;
            }

            .omni-bubble-content {
                overflow-wrap: anywhere;
                word-break: break-word;
                word-wrap: break-word;
                white-space: pre-wrap;
                min-width: 0;
                max-width: 100%;
            }

            .omni-bubble-text {
                overflow-wrap: anywhere;
                word-break: break-word;
                word-wrap: break-word;
                white-space: pre-wrap;
                min-width: 0;
                max-width: 100%;
                position: relative;
                transition: max-height 0.2s ease;
            }

            .omni-bubble-text.omni-clamped {
                max-height: 125px; /* Limite de aproximadamente 6 linhas */
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: rgba(0,0,0,0.2) transparent;
                padding-right: 4px;
            }
            .omni-bubble-text.omni-clamped::-webkit-scrollbar {
                width: 4px;
            }
            .omni-bubble-text.omni-clamped::-webkit-scrollbar-track {
                background: transparent;
            }
            .omni-bubble-text.omni-clamped::-webkit-scrollbar-thumb {
                background: rgba(0,0,0,0.2);
                border-radius: 4px;
            }

            .omni-read-more-btn {
                display: inline-block;
                color: #0284c7;
                font-size: 0.78rem;
                font-weight: 700;
                cursor: pointer;
                margin-top: 4px;
                user-select: none;
                transition: all 0.15s;
                background: rgba(2, 132, 199, 0.08);
                padding: 2px 8px;
                border-radius: 4px;
            }
            .omni-read-more-btn:hover {
                background: rgba(2, 132, 199, 0.18);
                color: #0369a1;
            }
            .omni-bubble.attendant .omni-read-more-btn {
                color: #047857;
                background: rgba(4, 120, 87, 0.1);
            }
            .omni-bubble.attendant .omni-read-more-btn:hover {
                color: #065f46;
                background: rgba(4, 120, 87, 0.2);
            }

            .omni-bubble-sender {
                font-size: 0.75rem;
                font-weight: 700;
                margin-bottom: 2px;
            }
            .omni-bubble.user .omni-bubble-sender, .omni-bubble.contact .omni-bubble-sender { color: #0284c7; }
            .omni-bubble.attendant .omni-bubble-sender { color: #047857; }

            .omni-bubble-time {
                font-size: 0.65rem;
                color: #94a3b8;
                float: right;
                margin-top: 4px;
                margin-left: 8px;
                display: inline-flex;
                align-items: center;
                gap: 2px;
            }

            .omni-check {
                font-size: 0.72rem;
                color: #53bdeb;
                font-weight: 700;
                letter-spacing: -1px;
                margin-left: 3px;
                display: inline-block;
                vertical-align: middle;
            }

            /* BOTTOM INPUT FOOTER */
            .omni-input-footer {
                background: #ffffff;
                border-top: 1px solid #cbd5e1;
                padding: 10px 16px;
                display: flex;
                align-items: center;
                gap: 10px;
                position: relative;
                flex-shrink: 0;
                min-width: 0;
                box-sizing: border-box;
            }

            #quote-preview-container, #edit-preview-container {
                min-width: 0;
                flex-shrink: 0;
                box-sizing: border-box;
                overflow: hidden;
            }

            .omni-footer-tools {
                display: flex;
                align-items: center;
                gap: 10px;
                color: #64748b;
                font-size: 1.2rem;
                flex-shrink: 0;
            }
            .omni-footer-tool-btn {
                cursor: pointer;
                transition: transform 0.1s, color 0.15s;
                user-select: none;
            }
            .omni-footer-tool-btn:hover { color: #0284c7; transform: scale(1.1); }

            .omni-message-input {
                flex: 1;
                min-width: 0;
                padding: 9px 16px;
                border: 1px solid #cbd5e1;
                border-radius: 20px;
                font-size: 0.88rem;
                outline: none;
                resize: none;
                font-family: inherit;
                line-height: 1.4;
                height: 38px;
                max-height: 114px;
                overflow-y: auto;
                box-sizing: border-box;
            }
            .omni-message-input:focus { border-color: #0284c7; }

            .omni-send-btn {
                width: 38px;
                height: 38px;
                min-width: 38px;
                border-radius: 50%;
                background: #0284c7;
                color: #ffffff;
                border: none;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 1.05rem;
                flex-shrink: 0;
                transition: background 0.15s;
            }
            .omni-send-btn:hover { background: #0369a1; }
            .omni-send-btn:disabled { background: #cbd5e1; cursor: not-allowed; }

            /* POP-OVERS DE EMOJI E RESPOSTAS RÁPIDAS */
            .omni-popover {
                position: absolute;
                bottom: 60px;
                left: 16px;
                background: #ffffff;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
                padding: 12px;
                z-index: 100;
                display: none;
            }

            .omni-emoji-grid {
                display: grid;
                grid-template-columns: repeat(6, 1fr);
                gap: 8px;
                width: 240px;
            }
            .omni-emoji-item {
                font-size: 1.3rem;
                text-align: center;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: background 0.15s;
            }
            .omni-emoji-item:hover { background: #f1f5f9; }

            .omni-canned-list {
                display: flex;
                flex-direction: column;
                gap: 6px;
                width: 320px;
                max-height: 250px;
                overflow-y: auto;
            }
            .omni-canned-item {
                padding: 8px 12px;
                font-size: 0.83rem;
                color: #334155;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.15s;
            }
            .omni-canned-item:hover {
                background: #e0f2fe;
                border-color: #0284c7;
                color: #0369a1;
            }
            .omni-vcard {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 12px;
                margin-top: 8px;
                margin-bottom: 8px;
                width: 280px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            }
            .omni-vcard-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }
            .omni-vcard-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: linear-gradient(135deg, #0ea5e9, #0284c7);
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 1.1rem;
            }
            .omni-vcard-info {
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .omni-vcard-name {
                font-weight: 600;
                color: #1e293b;
                font-size: 0.95rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .omni-vcard-phone {
                color: #64748b;
                font-size: 0.8rem;
            }
            .omni-vcard-actions {
                display: flex;
                gap: 8px;
            }
            .omni-vcard-btn {
                flex: 1;
                padding: 6px;
                background: #e2e8f0;
                color: #475569;
                border: none;
                border-radius: 6px;
                font-size: 0.8rem;
                font-weight: 600;
                cursor: pointer;
                text-align: center;
                transition: all 0.15s;
            }
            .omni-vcard-btn:hover { background: #cbd5e1; }
            .omni-vcard-btn-primary {
                background: #0ea5e9;
                color: #fff;
            }
            .omni-vcard-btn-primary:hover {
                background: #0284c7;
            }

            @keyframes pulse {
                0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
                50% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
                100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
            }

            /* Custom Audio Player CSS */
            .omni-custom-audio-player {
                color: #fff;
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 2px 0;
                width: 260px;
                max-width: 100%;
                box-sizing: border-box;
            }
            .omni-custom-audio-player .omni-play-btn {
                cursor: pointer;
                font-size: 1.25rem;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                line-height: 1;
                user-select: none;
                flex-shrink: 0;
            }
            .omni-custom-audio-player .omni-current-time {
                font-size: 0.7rem;
                font-weight: 600;
                line-height: 1;
            }

            .omni-bubble.contact .omni-custom-audio-player,
            .omni-bubble.contact .omni-custom-audio-player .omni-current-time,
            .omni-bubble.contact .omni-custom-audio-player .omni-play-btn {
                color: #475569 !important;
            }
            .omni-bubble.contact .omni-custom-audio-player button {
                background: #e2e8f0 !important;
                color: #475569 !important;
            }
            .omni-bubble.attendant .omni-custom-audio-player button {
                background: rgba(0,0,0,0.2) !important;
                color: #1e293b !important;
            }

            .omni-audio-slider {
                -webkit-appearance: none !important;
                appearance: none !important;
                width: 100% !important;
                height: 4px !important;
                background: rgba(0, 0, 0, 0.15);
                border-radius: 4px !important;
                outline: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                cursor: pointer !important;
                box-sizing: border-box !important;
            }
            .omni-audio-slider::-webkit-slider-runnable-track {
                width: 100%;
                height: 4px;
                cursor: pointer;
                border-radius: 4px;
                border: none;
                background: transparent;
                padding: 0;
                margin: 0;
            }
            .omni-audio-slider::-webkit-slider-thumb {
                -webkit-appearance: none !important;
                appearance: none !important;
                width: 12px !important;
                height: 12px !important;
                border-radius: 50% !important;
                background: #0284c7 !important;
                cursor: pointer !important;
                margin-top: -4px !important;
                border: 2px solid #ffffff !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
                box-sizing: border-box !important;
            }
            .omni-audio-slider::-moz-range-track {
                width: 100%;
                height: 4px;
                cursor: pointer;
                border-radius: 4px;
                border: none;
                background: transparent;
                padding: 0;
                margin: 0;
            }
            .omni-audio-slider::-moz-range-thumb {
                width: 12px !important;
                height: 12px !important;
                border-radius: 50% !important;
                background: #0284c7 !important;
                cursor: pointer !important;
                border: 2px solid #ffffff !important;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
                box-sizing: border-box !important;
            }

            .omni-msg-menu-btn {
                display: none;
                position: absolute;
                top: 4px;
                right: 8px;
                cursor: pointer;
                color: rgba(0,0,0,0.4);
                font-size: 1.2rem;
                padding: 0 4px;
                border-radius: 4px;
                z-index: 10;
            }
            .omni-bubble:hover .omni-msg-menu-btn {
                display: block;
            }
            .omni-bubble.attendant .omni-msg-menu-btn {
                color: rgba(255,255,255,0.7);
            }
            .omni-msg-dropdown {
                position: absolute;
                background: #fff;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-radius: 8px;
                padding: 4px 0;
                z-index: 9999;
                min-width: 150px;
                display: none;
            }
            .omni-msg-dropdown-item {
                padding: 8px 16px;
                font-size: 0.85rem;
                color: #334155;
                cursor: pointer;
                transition: background 0.1s;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .omni-msg-dropdown-item:hover {
                background: #f1f5f9;
            }
        </style>

        <div class="omni-app">
            <!-- TOP NAVBAR -->
            <div class="omni-navbar">
                <div class="omni-brand">
                    <span>URE Omnichannel</span>
                </div>
                <div class="omni-nav-tools">
                    <div class="omni-nav-icon active" title="Conversas / Chat">💬</div>
                    <div class="omni-nav-icon" title="Contatos">👥</div>
                    <div class="omni-nav-icon" title="Tags / Etiquetas">🏷️</div>
                    <div class="omni-nav-icon" title="Configurações" style="cursor:pointer;" onclick="openSettingsModal()">⚙️</div>
                    <div class="omni-user-badge">TI</div>
                </div>
            </div>

            <!-- BODY LAYOUT -->
            <div class="omni-body">
                <!-- SIDEBAR ESQUERDA -->
                <div class="omni-sidebar">
                    <div class="omni-sidebar-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span>Conversas</span>
                        <div style="display: flex; gap: 8px;">
                            <span style="font-size:0.9rem; color:#94a3b8; cursor:pointer;" onclick="loadChats()">🔄</span>
                        </div>
                    </div>

                    <div class="omni-search-box">
                        <div class="omni-search-input-wrap">
                            <span>🔍</span>
                            <input type="text" class="omni-search-input" id="search-input" placeholder="Pesquisar por nome ou número..." oninput="filterChatList()">
                        </div>
                        <div class="omni-filter-btn" title="Filtros">🔻</div>
                    </div>

                    <!-- 3 ABAS -->
                    <div class="omni-tabs">
                        <button class="omni-tab-btn active" onclick="switchTab('queue', this)">
                            📥 Fila <span class="omni-tab-badge" id="badge-queue">0</span>
                        </button>
                        <button class="omni-tab-btn" onclick="switchTab('mine', this)">
                            💬 Chats <span class="omni-tab-badge" id="badge-mine">0</span>
                        </button>
                        <button class="omni-tab-btn" onclick="switchTab('all', this)">
                            👥 Contatos
                        </button>
                    </div>

                    <div class="omni-subsort">
                        <span>Ordenar chamados por:</span>
                        <span>Opções de ordenação ↕</span>
                    </div>

                    <!-- LISTA DE CHATS -->
                    <div class="omni-chat-list" id="chat-list">
                        <div style="padding:20px; text-align:center; color:#94a3b8; font-size:0.85rem;">Carregando atendimentos...</div>
                    </div>
                </div>

                <!-- MAIN CHAT WINDOW -->
                <div class="omni-main">
                    <div class="omni-chat-header" id="main-chat-header" style="display:none;">
                        <div class="omni-header-contact" onclick="toggleContactDrawer()" title="Clique para mostrar os dados do contato">
                            <div class="omni-avatar" id="header-avatar">?</div>
                            <div>
                                <div style="font-weight:700; font-size:0.95rem; color:#0f172a;" id="header-title">Contato</div>
                                <div style="font-size:0.78rem; color:#64748b;" id="header-sub">clique para mostrar os dados do contato</div>
                            </div>
                        </div>

                        <div class="omni-header-tags">
                            <span class="omni-tag-badge omni-tag-whatsapp">🟢 Central WhatsApp</span>
                            <span class="omni-tag-badge omni-tag-dept">🏷️ Suporte URE TI</span>
                            <div id="header-actions" style="display:flex; gap:8px; margin-left:12px;"></div>
                        </div>
                    </div>

                    <div class="omni-messages-area" id="messages-box">
                        <div style="margin:auto; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            <div style="font-size:3rem; margin-bottom:10px;">💬</div>
                            <div>Selecione uma conversa ao lado para iniciar o atendimento</div>
                        </div>
                    </div>

                    <!-- PREVIEW DE ARQUIVOS -->
                    <div id="file-preview-container" style="display:none; background: #f8fafc; padding: 8px 16px; border-top: 1px solid #cbd5e1;">
                        <div id="file-preview-list"></div>
                    </div>

                    <!-- PREVIEW DE QUOTE (RESPONDER) -->
                    <div id="quote-preview-container" style="display:none; background: #f1f5f9; padding: 8px 12px; border-left: 4px solid #0ea5e9; border-top: 1px solid #cbd5e1; position: relative;">
                        <span style="position: absolute; top: 4px; right: 12px; cursor: pointer; color: #64748b; font-weight: bold; font-size: 1.2rem;" onclick="clearQuotePreview()">&times;</span>
                        <div id="quote-preview-name" style="font-size: 0.8rem; font-weight: bold; color: #0ea5e9; margin-bottom: 2px;"></div>
                        <div id="quote-preview-text" style="font-size: 0.8rem; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90%;"></div>
                    </div>

                    <!-- PREVIEW DE EDIÇÃO DE MENSAGEM -->
                    <div id="edit-preview-container" style="display:none; background: #fffbeb; padding: 8px 16px; border-left: 4px solid #f59e0b; border-top: 1px solid #fed7aa; position: relative;">
                        <span style="position: absolute; top: 6px; right: 14px; cursor: pointer; color: #b45309; font-weight: bold; font-size: 1.3rem; line-height: 1;" onclick="cancelEditMode()" title="Cancelar edição (Esc)">&times;</span>
                        <div style="font-size: 0.8rem; font-weight: 700; color: #b45309; margin-bottom: 2px; display: flex; align-items: center; gap: 6px;">
                            <span>✏️</span> <span>Editando mensagem enviada:</span>
                        </div>
                        <div id="edit-preview-original-text" style="font-size: 0.82rem; color: #78350f; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 88%; font-style: italic;"></div>
                    </div>

                    <div class="omni-input-footer">
                        <!-- POP-OVER EMOJIS -->
                        <div class="omni-popover" id="emoji-popover">
                            <div style="font-weight:700; font-size:0.8rem; color:#64748b; margin-bottom:8px;">SELECIONE UM EMOJI</div>
                            <div class="omni-emoji-grid">
                                <span class="omni-emoji-item" onclick="insertEmoji('😊')">😊</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('👍')">👍</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🚀')">🚀</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📋')">📋</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🙏')">🙏</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('✅')">✅</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📞')">📞</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('⏳')">⏳</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🤝')">🤝</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('💡')">💡</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📍')">📍</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('📄')">📄</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('⚠️')">⚠️</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('❌')">❌</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🤖')">🤖</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('👋')">👋</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('🎯')">🎯</span>
                                <span class="omni-emoji-item" onclick="insertEmoji('💻')">💻</span>
                            </div>
                        </div>

                        <!-- POP-OVER RESPOSTAS RÁPIDAS -->
                        <div class="omni-popover" id="canned-popover">
                            <div style="font-weight:700; font-size:0.8rem; color:#64748b; margin-bottom:8px;">RESPOSTAS RÁPIDAS</div>
                            <div class="omni-canned-list">
                                <div class="omni-canned-item" onclick="insertCanned('Olá! Como posso te ajudar hoje?')">
                                    💬 <strong>Saudação:</strong> Olá! Como posso te ajudar hoje?
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Seu chamado de suporte técnico está em andamento pela nossa equipe de TI.')">
                                    🛠️ <strong>Em Andamento:</strong> Seu chamado de suporte está em andamento.
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Aguarde um momento enquanto realizamos a verificação no sistema.')">
                                    ⏳ <strong>Aguarde:</strong> Aguarde um momento enquanto verificamos no sistema.
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Poderia nos enviar uma foto ou print do erro, por gentileza?')">
                                    📷 <strong>Solicitar Print:</strong> Poderia nos enviar um print do erro?
                                </div>
                                <div class="omni-canned-item" onclick="insertCanned('Atendimento concluído com sucesso. Qualquer dúvida estamos à disposição!')">
                                    ✅ <strong>Conclusão:</strong> Atendimento concluído com sucesso.
                                </div>
                            </div>
                        </div>

                        <!-- INPUT INVISÍVEL DE UPLOAD DE ARQUIVOS -->
                        <input type="file" id="file-input" style="display:none;" onchange="uploadSelectedFile(this)" multiple>

                        <div class="omni-footer-tools">
                            <span class="omni-footer-tool-btn" title="Anexar Arquivo/Foto" onclick="document.getElementById('file-input').click()">+</span>
                            <span class="omni-footer-tool-btn" title="Emojis" onclick="toggleOmniPopover('emoji-popover')">😊</span>
                            <span class="omni-footer-tool-btn" title="Respostas Rápidas" onclick="toggleOmniPopover('canned-popover')">⚡</span>
                            <span class="omni-footer-tool-btn" id="internal-note-btn" title="Nota Interna" onclick="toggleInternalNoteMode()">📝</span>
                        </div>

                        <textarea class="omni-message-input" id="message-input" placeholder="Digite uma mensagem (Shift + Enter quebra linha)..." onkeydown="handleKeyPress(event)" oninput="autoResizeInput(this); checkInputState();" disabled rows="1"></textarea>
                        <button class="omni-send-btn" id="send-btn" onclick="toggleAudioRecording()" disabled title="Gravar Áudio" style="transition: all 0.2s; min-width: 45px;">🎤</button>
                    </div>
                </div>

                <!-- GAVETA LATERAL DIREITA: DADOS DO CONTATO (WHATSAPP WEB STYLE) -->
                <div class="omni-contact-drawer" id="contact-drawer" style="display:none;">
                    <div class="omni-drawer-header">
                        <div class="omni-drawer-close-btn" onclick="closeContactDrawer()" title="Fechar dados do contato">✕</div>
                        <div class="omni-drawer-title">Dados do contato</div>
                    </div>
                    <div class="omni-drawer-body">
                        <!-- HERO CARD: FOTO GRANDE, NOME, TELEFONE E AÇÕES RÁPIDAS -->
                        <div class="omni-drawer-card omni-drawer-hero">
                            <div class="omni-drawer-avatar-wrap" onclick="openDrawerPhotoLightbox()" title="Clique para expandir a foto em tamanho real">
                                <img id="drawer-avatar-img" src="" alt="Foto" style="display:none;">
                                <div class="omni-drawer-avatar-fallback" id="drawer-avatar-fallback">?</div>
                                <div class="omni-drawer-avatar-overlay">🔍 Expandir Foto</div>
                            </div>
                            <div class="omni-drawer-name" id="drawer-name">Contato</div>
                            <div class="omni-drawer-phone" id="drawer-phone">+55 00 00000-0000</div>

                            <div class="omni-drawer-actions">
                                <button class="omni-drawer-action-btn" onclick="copyContactPhone()" title="Copiar telefone">
                                    <div class="omni-drawer-action-circle">📋</div>
                                    <span id="drawer-copy-btn-label">Copiar</span>
                                </button>
                                <button class="omni-drawer-action-btn" onclick="openGlpiTicketModal()" title="Abrir chamado no GLPI">
                                    <div class="omni-drawer-action-circle">🎫</div>
                                    <span>Chamado</span>
                                </button>
                                <button class="omni-drawer-action-btn" onclick="openTransferModal()" title="Transferir conversa">
                                    <div class="omni-drawer-action-circle">🔄</div>
                                    <span>Transferir</span>
                                </button>
                                <button class="omni-drawer-action-btn" onclick="triggerDrawerCloseChat()" title="Encerrar atendimento">
                                    <div class="omni-drawer-action-circle" style="color:#ef4444; background:#fee2e2;">📵</div>
                                    <span style="color:#ef4444;">Encerrar</span>
                                </button>
                            </div>
                        </div>

                        <!-- RECADO / STATUS DO WHATSAPP -->
                        <div class="omni-drawer-card">
                            <div class="omni-drawer-section-title">
                                <span>Recado</span>
                            </div>
                            <div class="omni-drawer-recado-text" id="drawer-recado">Disponível</div>
                            <div class="omni-drawer-recado-date" id="drawer-recado-date" style="display:none;"></div>
                        </div>

                        <!-- MÍDIA, LINKS E DOCS (COMO NO PRINT DO WHATSAPP) -->
                        <div class="omni-drawer-card">
                            <div class="omni-drawer-section-title">
                                <span style="display:flex; align-items:center; gap:8px;">
                                    <span>📁</span> Mídia, links e docs
                                </span>
                                <span id="drawer-media-count" style="font-size:0.85rem; color:#64748b; font-weight:700;">0</span>
                            </div>
                            <div class="omni-drawer-media-grid" id="drawer-media-grid">
                                <div style="font-size:0.82rem; color:#94a3b8; padding:8px 0;">Nenhuma mídia compartilhada</div>
                            </div>
                        </div>

                        <!-- DADOS DO ATENDIMENTO / GLPI -->
                        <div class="omni-drawer-card">
                            <div class="omni-drawer-section-title">
                                <span>Detalhes do Atendimento</span>
                            </div>
                            <div style="font-size:0.83rem; color:#475569; display:flex; flex-direction:column; gap:8px;">
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:#64748b;">Status:</span>
                                    <span id="drawer-chat-status" style="font-weight:600; text-transform:uppercase;">-</span>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:#64748b;">Atendente:</span>
                                    <span id="drawer-chat-tech" style="font-weight:600;">-</span>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:#64748b;">Última Modificação:</span>
                                    <span id="drawer-chat-start" style="font-weight:600;">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIGHTBOX MODAL (ZOOM IMAGEM) -->
        <div id="lightbox-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; justify-content:center; align-items:center; opacity:0; transition:opacity 0.2s;" onclick="closeLightbox()" onwheel="handleLightboxScroll(event)">
            <div style="position:absolute; top:20px; right:30px; display:flex; gap:15px; align-items:center;">
                <span style="color:#fff; font-size:30px; font-weight:bold; cursor:pointer; text-shadow: 0 0 10px rgba(0,0,0,0.5);" onclick="event.stopPropagation(); zoomLightbox(0.2)" title="Mais Zoom (Ou use o scroll do mouse)">+</span>
                <span style="color:#fff; font-size:35px; font-weight:bold; cursor:pointer; text-shadow: 0 0 10px rgba(0,0,0,0.5); line-height: 25px;" onclick="event.stopPropagation(); zoomLightbox(-0.2)" title="Menos Zoom (Ou use o scroll do mouse)">-</span>
                <span style="color:#fff; font-size:40px; font-weight:bold; cursor:pointer; text-shadow: 0 0 10px rgba(0,0,0,0.5); margin-left: 20px;" onclick="closeLightbox()" title="Fechar">&times;</span>
            </div>
            <img id="lightbox-img" style="max-width:90%; max-height:90%; border-radius:8px; box-shadow:0 0 20px rgba(0,0,0,0.5); transform:scale(0.9); transition:transform 0.15s ease-out;" src="" onclick="event.stopPropagation()">
        </div>

        <!-- MODAL DE TRANSFERÊNCIA -->
        <div id="transfer-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:400px; max-width:90%; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
                    <h3 style="margin:0; font-size:1.1rem; color:#1e293b;">🔄 Transferir Chat</h3>
                    <button onclick="closeTransferModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#64748b;">&times;</button>
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:5px; font-size:0.9rem; font-weight:600; color:#475569;">Selecione o destino:</label>
                    <select id="transfer-user-select" style="width:100%; padding:8px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">
                        <option value="0">📥 Fila (Desvincular)</option>
                    </select>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button onclick="closeTransferModal()" style="padding:8px 16px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer; font-size:0.9rem;">Cancelar</button>
                    <button id="transfer-submit-btn" onclick="submitTransfer()" style="padding:8px 16px; background:#0284c7; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; font-weight:600;">Confirmar</button>
                </div>
            </div>
        </div>

        <!-- MODAL DE ENCERRAMENTO -->
        <div id="close-chat-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:350px; max-width:90%; box-shadow:0 10px 25px rgba(0,0,0,0.2); text-align:center;">
                <div style="font-size:2rem; margin-bottom:10px;">📵</div>
                <h3 style="margin:0 0 10px 0; font-size:1.1rem; color:#1e293b;">Encerrar Atendimento</h3>
                <p style="margin:0 0 20px 0; font-size:0.9rem; color:#64748b;">Tem certeza que deseja encerrar e arquivar esta conversa?</p>
                <div style="display:flex; justify-content:center; gap:10px;">
                    <button onclick="document.getElementById('close-chat-modal').style.display='none'" style="padding:8px 16px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; cursor:pointer; font-size:0.9rem;">Cancelar</button>
                    <button id="close-chat-confirm-btn" onclick="submitCloseChat()" style="padding:8px 16px; background:#ef4444; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; font-weight:600;">Sim, Encerrar</button>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIGURAÇÕES -->
        <div id="settings-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
            <div style="background:#fff; border-radius:8px; width:800px; max-width:95%; height:600px; max-height:90vh; box-shadow:0 10px 25px rgba(0,0,0,0.2); display:flex; flex-direction:column; overflow:hidden;">
                <!-- Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; padding:15px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <h3 style="margin:0; font-size:1.1rem; color:#1e293b; display:flex; align-items:center; gap:8px;">⚙️ Configurações</h3>
                    <button onclick="closeSettingsModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
                </div>
                <!-- Body -->
                <div style="display:flex; flex:1; overflow:hidden;">
                    <!-- Menu lateral -->
                    <div style="width:220px; background:#f1f5f9; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; padding:15px 0;">
                        <button class="settings-tab-btn active" onclick="switchSettingsTab('settings-tab-newchat')" style="padding:10px 20px; text-align:left; background:#e0f2fe; border:none; border-right:3px solid #0284c7; color:#0369a1; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:8px;">
                            ➕ Novo Contato
                        </button>
                        <button class="settings-tab-btn" style="padding:10px 20px; text-align:left; background:transparent; border:none; border-right:3px solid transparent; color:#475569; font-weight:500; cursor:pointer; display:flex; align-items:center; gap:8px;">
                            🤖 Respostas Rápidas
                        </button>
                    </div>
                    <!-- Conteúdo -->
                    <div style="flex:1; padding:25px; overflow-y:auto; background:#fff;">
                        <!-- ABA NOVO CONTATO -->
                        <div id="settings-tab-newchat" class="settings-content-pane">
                            <h4 style="margin-top:0; margin-bottom:20px; color:#1e293b; font-size:1.1rem;">Iniciar Nova Conversa</h4>
                            <p style="color:#64748b; font-size:0.9rem; margin-bottom:20px;">Envie uma mensagem ativa para um contato que ainda não está no sistema.</p>
                            
                            <div style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:5px; font-size:0.9rem; font-weight:600; color:#475569;">Número do WhatsApp (com DDI e DDD):</label>
                                <input type="text" id="new-chat-phone" value="55" placeholder="Ex: 5517999999999" style="width:100%; max-width:400px; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.95rem;">
                                <small style="display:block; color:#64748b; font-size:0.8rem; margin-top:5px;">Apenas números. Ex: 55 para Brasil.</small>
                            </div>
                            <div style="margin-bottom:25px;">
                                <label style="display:block; margin-bottom:5px; font-size:0.9rem; font-weight:600; color:#475569;">Nome do Contato (Opcional):</label>
                                <input type="text" id="new-chat-name" placeholder="Ex: João Silva" style="width:100%; max-width:400px; padding:10px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.95rem;">
                            </div>
                            <div id="new-chat-error" style="color:#ef4444; font-size:0.85rem; margin-bottom:15px; display:none; background:#fef2f2; padding:10px; border-radius:4px; border:1px solid #fecaca; max-width:400px;"></div>
                            
                            <button id="new-chat-submit-btn" onclick="submitNewChat()" style="padding:10px 24px; background:#0284c7; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:0.95rem; font-weight:600; transition:background 0.2s;">Iniciar Conversa</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            let currentTab = 'queue';
            let activeChatId = 0;
            let activePhoneNumber = '';
            let isContactTabActive = false;
            let activeChatOwnerId = 0;
            const currentUserId = <?= json_encode((int) Session::getLoginUserID()) ?>;
            let allLoadedChats = [];
            let stagedFiles = [];
            const rootDoc = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.root_doc) ? CFG_GLPI.root_doc : '';
            const CAN_TRANSFER = <?= json_encode($canTransfer) ?>;
            let transferUsersLoaded = false;
            let currentLightboxZoom = 1;

            const audioBlobCache = {};
            function getAudioBlobUrl(dataUrl) {
                if (audioBlobCache[dataUrl]) return audioBlobCache[dataUrl];
                try {
                    const parts = dataUrl.split(',');
                    const mime = parts[0].split(':')[1].replace(';base64', '');
                    const bstr = atob(parts[1]);
                    const n = bstr.length;
                    const u8arr = new Uint8Array(n);
                    for (let i = 0; i < n; i++) {
                        u8arr[i] = bstr.charCodeAt(i);
                    }
                    const blob = new Blob([u8arr], { type: mime });
                    const url = URL.createObjectURL(blob);
                    audioBlobCache[dataUrl] = url;
                    return url;
                } catch(e) {
                    console.error("Falha ao converter audio dataURI para Blob", e);
                    return dataUrl;
                }
            }

            function openLightbox(src) {
                const modal = document.getElementById('lightbox-modal');
                const img = document.getElementById('lightbox-img');
                img.src = src;
                currentLightboxZoom = 1;
                modal.style.display = 'flex';
                // Trigger reflow for CSS animation
                void modal.offsetWidth;
                modal.style.opacity = '1';
                img.style.transform = `scale(${currentLightboxZoom})`;
            }

            function closeLightbox() {
                const modal = document.getElementById('lightbox-modal');
                const img = document.getElementById('lightbox-img');
                modal.style.opacity = '0';
                img.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    modal.style.display = 'none';
                    img.src = '';
                }, 200);
            }

            function zoomLightbox(delta) {
                currentLightboxZoom += delta;
                if (currentLightboxZoom < 0.2) currentLightboxZoom = 0.2;
                if (currentLightboxZoom > 5) currentLightboxZoom = 5;
                const img = document.getElementById('lightbox-img');
                img.style.transform = `scale(${currentLightboxZoom})`;
            }

            function handleLightboxScroll(e) {
                e.preventDefault();
                const delta = e.deltaY < 0 ? 0.1 : -0.1;
                zoomLightbox(delta);
            }

            function switchTab(tab, btn, keepChat = false) {
                currentTab = tab;
                isContactTabActive = (tab === 'all');
                document.querySelectorAll('.omni-tab-btn').forEach(b => b.classList.remove('active'));
                if (btn) btn.classList.add('active');
                
                if (!keepChat) {
                    // [UX] Resetar o estado do chat ao mudar de aba
                    cancelEditMode();
                    clearQuotePreview();
                    closeContactDrawer();
                    activeChatId = 0;
                    activePhoneNumber = '';
                    document.getElementById('main-chat-header').style.display = 'none';
                    document.getElementById('messages-box').innerHTML = `
                        <div style="margin:auto; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            <div style="font-size:3rem; margin-bottom:10px;">💬</div>
                            <div>Selecione uma conversa ao lado para iniciar o atendimento</div>
                        </div>
                    `;
                    document.getElementById('message-input').disabled = true;
                    document.getElementById('send-btn').disabled = true;
                }
                
                loadChats();
            }

            // [UX] Resetar chat ao apertar ESC (cancela edição/quote/lightbox/drawer primeiro se ativo)
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const lightbox = document.getElementById('lightbox-modal');
                    if (lightbox && lightbox.style.display === 'flex') {
                        closeLightbox();
                        return;
                    }
                    const drawer = document.getElementById('contact-drawer');
                    if (drawer && drawer.style.display !== 'none') {
                        closeContactDrawer();
                        return;
                    }
                    if (currentEditingMsg) {
                        cancelEditMode();
                        return;
                    }
                    if (currentQuote) {
                        clearQuotePreview();
                        return;
                    }
                    closeContactDrawer();
                    activeChatId = 0;
                    activePhoneNumber = '';
                    document.getElementById('main-chat-header').style.display = 'none';
                    document.getElementById('messages-box').innerHTML = `
                        <div style="margin:auto; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            <div style="font-size:3rem; margin-bottom:10px;">💬</div>
                            <div>Selecione uma conversa ao lado para iniciar o atendimento</div>
                        </div>
                    `;
                    document.getElementById('message-input').disabled = true;
                    document.getElementById('send-btn').disabled = true;
                    loadChats();
                }
            });

            async function safeFetchJson(url, options = {}) {
                try {
                    const csrfMeta = document.querySelector('meta[property="glpi:csrf_token"]');
                    if (csrfMeta) {
                        const token = csrfMeta.getAttribute('content');
                        
                        // 1. Sempre adiciona na URL
                        if (url.includes('?')) {
                            url += '&_glpi_csrf_token=' + token;
                        } else {
                            url += '?_glpi_csrf_token=' + token;
                        }

                        // 2. Sempre adiciona no Header (O GLPI as vezes depende exclusivamente dele)
                        options.headers = options.headers || {};
                        options.headers['X-Glpi-Csrf-Token'] = token;
                        options.headers['X-Requested-With'] = 'XMLHttpRequest'; // Garante que o GLPI saiba que é AJAX

                        // 3. Sempre adiciona no FormData se aplicável
                        if (options.body && typeof options.body.append === 'function') {
                            options.body.append('_glpi_csrf_token', token);
                        }
                    }
                    const res = await fetch(url, options);
                    const text = await res.text();
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        console.error('Resposta nao-JSON do servidor:', text);
                        return { success: false, error: 'Servidor retornou formato invalido. Verifique o log whatsappsimples.log' };
                    }
                } catch(e) {
                    console.error('Erro na chamada AJAX:', e);
                    return { success: false, error: 'Falha na conexao de rede' };
                }
            }

            function updateAllTabBadges(counts, currentChats) {
                if (!counts) return;

                const elQueue = document.getElementById('badge-queue');
                if (elQueue) {
                    const qCount = Number(counts.queue) || 0;
                    elQueue.innerText = qCount;
                    elQueue.classList.toggle('alert', qCount > 0);
                }

                const elMine = document.getElementById('badge-mine');
                if (elMine) {
                    const mCount = Number(counts.mine) || 0;
                    elMine.innerText = mCount;
                    // Alerta se houver mensagens pendentes de leitura
                    let hasUnread = false;
                    if (currentTab === 'mine' && Array.isArray(currentChats)) {
                        hasUnread = currentChats.some(c => c.unread_count > 0 && c.id !== activeChatId);
                    } else {
                        hasUnread = (Number(counts.mine_unread) || 0) > 0;
                    }
                    elMine.classList.toggle('alert', hasUnread);
                }
            }

            async function loadChats() {
                const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/chats.php?tab=${currentTab}`);
                if (!data) return;
                allLoadedChats = data.chats || [];

                if (data.counts) {
                    updateAllTabBadges(data.counts, allLoadedChats);
                }

                renderChatList(allLoadedChats);

                // Auto-atualiza a janela principal se houver um chat aberto
                if (activeChatId || activePhoneNumber) {
                    // Verifica se o chat ativo recebeu mensagens novas no poll para zerar o badge silenciosamente
                    const activeObj = allLoadedChats.find(c => c.id === activeChatId || c.phone_number === activePhoneNumber);
                    if (activeObj && activeObj.unread_count > 0) {
                        activeObj.unread_count = 0;
                        resetUnreadCount(activeObj.id);
                    }
                    loadMessages(isContactTabActive);
                }
            }

            function filterChatList() {
                const query = document.getElementById('search-input').value.toLowerCase().trim();
                if (!query) {
                    renderChatList(allLoadedChats);
                    return;
                }

                const filtered = allLoadedChats.filter(c => 
                    (c.contact_name && c.contact_name.toLowerCase().includes(query)) ||
                    (c.phone_number && c.phone_number.includes(query))
                );
                renderChatList(filtered);
            }

            let currentContactProfile = null;

            function updateHeaderAvatar(name, phone, pictureUrl) {
                const headerAvatar = document.getElementById('header-avatar');
                if (!headerAvatar) return;
                const initials = getInitials(name || phone);
                const cleanPhone = (phone || '').replace(/[^0-9]/g, '');
                const src = pictureUrl || (cleanPhone ? `${rootDoc}/plugins/whatsappsimples/front/avatar.php?phone=${cleanPhone}` : '');

                if (src) {
                    headerAvatar.innerHTML = `
                        <img src="${src}" alt="${initials}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" style="width:100%; height:100%; border-radius:50%; object-fit:cover; cursor:pointer;" onclick="event.stopPropagation(); openLightbox('${src}')" title="Clique para ver a foto">
                        <span style="display:none; width:100%; height:100%; border-radius:50%; align-items:center; justify-content:center; background:#0284c7; color:#fff;">${initials}</span>
                    `;
                } else {
                    headerAvatar.innerHTML = `<span style="display:flex; width:100%; height:100%; border-radius:50%; align-items:center; justify-content:center; background:#0284c7; color:#fff;">${initials}</span>`;
                }
            }

            function toggleContactDrawer() {
                const drawer = document.getElementById('contact-drawer');
                if (!drawer) return;
                if (drawer.style.display === 'none' || drawer.style.display === '') {
                    openContactDrawer();
                } else {
                    closeContactDrawer();
                }
            }

            function openContactDrawer() {
                const drawer = document.getElementById('contact-drawer');
                if (!drawer) return;
                drawer.style.display = 'flex';
                loadContactDrawerData(activePhoneNumber, activeChatId);
            }

            function closeContactDrawer() {
                const drawer = document.getElementById('contact-drawer');
                if (drawer) {
                    drawer.style.display = 'none';
                }
            }

            function openDrawerPhotoLightbox() {
                const img = document.getElementById('drawer-avatar-img');
                if (img && img.style.display !== 'none' && img.src) {
                    openLightbox(img.src);
                }
            }

            function copyContactPhone() {
                if (!activePhoneNumber) return;
                const clean = activePhoneNumber.replace(/[^0-9]/g, '');
                const toCopy = clean.startsWith('55') ? `+${clean}` : activePhoneNumber;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(toCopy).then(() => {
                        const label = document.getElementById('drawer-copy-btn-label');
                        if (label) {
                            const original = label.innerText;
                            label.innerText = 'Copiado! ✓';
                            label.style.color = '#22c55e';
                            setTimeout(() => {
                                label.innerText = original;
                                label.style.color = '';
                            }, 1800);
                        }
                    }).catch(e => {
                        prompt('Copie o número:', toCopy);
                    });
                } else {
                    prompt('Copie o número:', toCopy);
                }
            }

            function openGlpiTicketModal() {
                window.open(`${rootDoc}/front/ticket.form.php`, '_blank');
            }

            function triggerDrawerCloseChat() {
                if (activeChatId > 0) {
                    closeActiveChat(activeChatId);
                }
            }

            function formatPhoneNumber(phone) {
                if (!phone) return '';
                if (phone.includes('@lid')) {
                    return 'Número Privado (Meta LID)';
                }
                const digits = phone.replace(/[^0-9]/g, '');
                if (digits.startsWith('55') && (digits.length === 12 || digits.length === 13)) {
                    const ddd = digits.substring(2, 4);
                    const num = digits.substring(4);
                    if (num.length === 9) {
                        return `+55 ${ddd} ${num.substring(0, 5)}-${num.substring(5)}`;
                    } else if (num.length === 8) {
                        return `+55 ${ddd} ${num.substring(0, 4)}-${num.substring(4)}`;
                    }
                }
                return phone;
            }

            function renderDrawerMediaPreviewsFromCurrentChat() {
                const grid = document.getElementById('drawer-media-grid');
                const countEl = document.getElementById('drawer-media-count');
                const box = document.getElementById('messages-box');
                if (!grid || !box) return;

                const mediaNodes = box.querySelectorAll('img[onclick*="openLightbox"], .omni-custom-audio-player, video');
                if (countEl) countEl.innerText = mediaNodes.length;

                if (mediaNodes.length === 0) {
                    grid.innerHTML = '<div style="font-size:0.82rem; color:#94a3b8; padding:8px 0;">Nenhuma mídia compartilhada</div>';
                    return;
                }

                let html = '';
                mediaNodes.forEach(node => {
                    if (node.tagName.toLowerCase() === 'img') {
                        html += `
                            <div class="omni-drawer-media-thumb" onclick="openLightbox('${node.src}')" title="Foto">
                                <img src="${node.src}" alt="Foto">
                            </div>
                        `;
                    } else if (node.tagName.toLowerCase() === 'video') {
                        html += `
                            <div class="omni-drawer-media-thumb" style="background:#0f172a; color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;" onclick="window.open('${node.src}', '_blank')" title="Vídeo">
                                <span style="font-size:1.4rem;">🎬</span>
                                <span style="font-size:0.65rem; font-weight:600;">Vídeo</span>
                            </div>
                        `;
                    } else {
                        html += `
                            <div class="omni-drawer-media-thumb" style="background:#f1f5f9; color:#475569; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;" title="Áudio">
                                <span style="font-size:1.4rem;">🎵</span>
                                <span style="font-size:0.65rem; font-weight:600;">Áudio</span>
                            </div>
                        `;
                    }
                });
                grid.innerHTML = html;
            }

            function renderDrawerMediaPreviews(previews, total) {
                const grid = document.getElementById('drawer-media-grid');
                const countEl = document.getElementById('drawer-media-count');
                if (countEl) countEl.innerText = total || (previews ? previews.length : 0);
                if (!grid) return;

                if (!previews || previews.length === 0) {
                    grid.innerHTML = '<div style="font-size:0.82rem; color:#94a3b8; padding:8px 0;">Nenhuma mídia compartilhada</div>';
                    return;
                }

                grid.innerHTML = previews.map(m => {
                    if (m.type === 'image') {
                        return `
                            <div class="omni-drawer-media-thumb" onclick="openLightbox('${m.url}')" title="${escapeHtml(m.caption || 'Foto')}">
                                <img src="${m.url}" alt="Foto" loading="lazy">
                            </div>
                        `;
                    } else if (m.type === 'video') {
                        return `
                            <div class="omni-drawer-media-thumb" style="background:#0f172a; color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;" onclick="window.open('${m.url}', '_blank')" title="Vídeo">
                                <span style="font-size:1.4rem;">🎬</span>
                                <span style="font-size:0.65rem; font-weight:600;">Vídeo</span>
                            </div>
                        `;
                    } else if (m.type === 'audio') {
                        return `
                            <div class="omni-drawer-media-thumb" style="background:#f1f5f9; color:#475569; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;" title="Áudio">
                                <span style="font-size:1.4rem;">🎵</span>
                                <span style="font-size:0.65rem; font-weight:600;">Áudio</span>
                            </div>
                        `;
                    } else {
                        return `
                            <div class="omni-drawer-media-thumb" style="background:#f1f5f9; color:#475569; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;" onclick="window.open('${m.url}', '_blank')" title="Documento">
                                <span style="font-size:1.4rem;">📄</span>
                                <span style="font-size:0.65rem; font-weight:600;">Doc</span>
                            </div>
                        `;
                    }
                }).join('');
            }

            async function loadContactDrawerData(phone, chatId) {
                if (!phone && !chatId) return;
                const drawer = document.getElementById('contact-drawer');
                if (!drawer) return;

                const chatObj = allLoadedChats.find(c => c.id === chatId || c.phone_number === phone);
                const displayName = (chatObj && chatObj.contact_name) ? chatObj.contact_name : (phone || 'Contato');
                const initials = getInitials(displayName);

                document.getElementById('drawer-name').innerText = displayName;
                document.getElementById('drawer-phone').innerText = formatPhoneNumber(phone);
                document.getElementById('drawer-avatar-fallback').innerText = initials;
                document.getElementById('drawer-avatar-fallback').style.display = 'flex';
                document.getElementById('drawer-avatar-img').style.display = 'none';
                document.getElementById('drawer-recado').innerText = (chatObj && chatObj.contact_status) ? chatObj.contact_status : 'Disponível';

                const statusLabelMap = {
                    'in_progress': '🟢 Em Atendimento',
                    'pending': '🟡 Na Fila',
                    'closed': '⚪ Encerrado'
                };
                const currentStatus = chatObj ? chatObj.status : 'in_progress';
                document.getElementById('drawer-chat-status').innerText = statusLabelMap[currentStatus] || currentStatus;
                document.getElementById('drawer-chat-tech').innerText = (chatObj && chatObj.technician_name) ? chatObj.technician_name : 'Sem Atendente';
                document.getElementById('drawer-chat-start').innerText = (chatObj && chatObj.date_mod) ? formatCardTime(chatObj.date_mod) : '-';

                renderDrawerMediaPreviewsFromCurrentChat();

                // Busca dados atualizados da API em background
                const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/contact-profile.php?phone_number=${encodeURIComponent(phone)}&chat_id=${chatId || 0}`);
                if (!data || !data.success) return;

                currentContactProfile = data;

                if (data.name) {
                    document.getElementById('drawer-name').innerText = data.name;
                }
                if (data.picture_url) {
                    const img = document.getElementById('drawer-avatar-img');
                    img.src = data.picture_url;
                    img.onload = () => {
                        img.style.display = 'block';
                        document.getElementById('drawer-avatar-fallback').style.display = 'none';
                    };
                    img.onerror = () => {
                        img.style.display = 'none';
                        document.getElementById('drawer-avatar-fallback').style.display = 'flex';
                    };
                    updateHeaderAvatar(data.name || displayName, phone, data.picture_url);
                } else {
                    document.getElementById('drawer-avatar-img').style.display = 'none';
                    document.getElementById('drawer-avatar-fallback').style.display = 'flex';
                }

                if (data.status) {
                    document.getElementById('drawer-recado').innerText = data.status;
                } else {
                    document.getElementById('drawer-recado').innerText = 'Disponível';
                }

                if (data.set_at) {
                    const dateEl = document.getElementById('drawer-recado-date');
                    dateEl.style.display = 'block';
                    dateEl.innerText = 'Definido em ' + formatFullDateTime(data.set_at);
                } else {
                    document.getElementById('drawer-recado-date').style.display = 'none';
                }

                if (data.media_previews && data.media_previews.length > 0) {
                    renderDrawerMediaPreviews(data.media_previews, data.total_media);
                }
            }

            function renderChatList(chats) {
                const listEl = document.getElementById('chat-list');

                if (!chats || chats.length === 0) {
                    listEl.innerHTML = '<div style="padding:20px; text-align:center; color:#94a3b8; font-size:0.85rem;">Nenhum registro encontrado</div>';
                    return;
                }

                listEl.innerHTML = chats.map(c => {
                    const initials = getInitials(c.contact_name || c.phone_number);
                    const isSelected = (c.id === activeChatId || c.phone_number === activePhoneNumber);
                    
                    let displayPhone = c.phone_number;
                    if (displayPhone && displayPhone.includes('@lid')) {
                        displayPhone = 'Número Privado (Meta)';
                    }

                    const cleanPhone = (c.phone_number || '').replace(/[^0-9]/g, '');
                    const avatarSrc = c.profile_picture_url || (cleanPhone ? `${rootDoc}/plugins/whatsappsimples/front/avatar.php?phone=${cleanPhone}` : '');

                    return `
                        <div class="omni-chat-card ${isSelected ? 'selected' : ''}" onclick="openChat(${c.id}, '${escapeJs(c.contact_name)}', '${escapeJs(c.phone_number)}', ${isContactTabActive}, '${escapeJs(c.technician_name || 'Sem Atendente')}', ${c.users_id})">
                            <div class="omni-avatar-wrap">
                                <div class="omni-avatar" onclick="event.stopPropagation(); if ('${avatarSrc}') openLightbox('${avatarSrc}'); else openChat(${c.id}, '${escapeJs(c.contact_name)}', '${escapeJs(c.phone_number)}', ${isContactTabActive}, '${escapeJs(c.technician_name || 'Sem Atendente')}', ${c.users_id});" title="${avatarSrc ? 'Clique para expandir a foto' : ''}">
                                    ${avatarSrc ? `<img src="${avatarSrc}" alt="${initials}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : ''}
                                    <span style="${avatarSrc ? 'display:none;' : 'display:flex;'} width:100%; height:100%; align-items:center; justify-content:center;">${initials}</span>
                                </div>
                                <div class="omni-avatar-icon">💬</div>
                            </div>
                            <div class="omni-card-info">
                                <div class="omni-card-row1">
                                    <div class="omni-card-name">${escapeHtml(c.contact_name)}</div>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        ${(!isSelected && c.unread_count > 0) ? `<span style="background:#ef4444; color:#fff; font-size:0.7rem; font-weight:700; padding:1px 6px; border-radius:10px;">${c.unread_count}</span>` : ''}
                                        <div class="omni-card-time" title="${formatFullDateTime(c.date_mod)}">${formatCardTime(c.date_mod)}</div>
                                    </div>
                                </div>
                                <div class="omni-card-row2">
                                    ${isContactTabActive ? '' : '<span>✓✓</span>'}
                                    <span>${displayPhone}</span>
                                    ${isContactTabActive && c.technician_name ? `<span style="margin-left:auto; font-size:0.72rem; color:#0284c7; font-weight:600; white-space:nowrap;">👤 ${escapeHtml(c.technician_name)}</span>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            async function resetUnreadCount(chatId) {
                if (!chatId || chatId <= 0) return;
                const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                const formData = new FormData();
                formData.append('chat_id', chatId);
                await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/reset-unread.php`, {
                    method: 'POST',
                    body: formData
                });
            }

            async function openChat(chatId, name, phone, isContactTab = false, technicianName = 'Sem Atendente', ownerId = 0) {
                cancelEditMode();
                clearQuotePreview();
                if (activeChatId !== chatId || activePhoneNumber !== phone) {
                    document.getElementById('messages-box').innerHTML = '';
                }
                
                activeChatId = chatId;
                activePhoneNumber = phone;
                activeChatOwnerId = ownerId;

                // Zera o contador visualmente e no banco
                const chatObj = allLoadedChats.find(c => c.id === chatId);
                if (chatObj && chatObj.unread_count > 0) {
                    chatObj.unread_count = 0;
                    resetUnreadCount(chatId);
                }

                document.getElementById('main-chat-header').style.display = 'flex';
                let displayPhone = phone;
                if (displayPhone && displayPhone.includes('@lid')) {
                    displayPhone = 'Número Privado (Meta)';
                }
                
                document.getElementById('header-title').innerText = name || displayPhone;
                document.getElementById('header-sub').innerText = 'clique para mostrar os dados do contato';
                
                updateHeaderAvatar(name || displayPhone, phone, (chatObj ? chatObj.profile_picture_url : null));

                const drawer = document.getElementById('contact-drawer');
                if (drawer && drawer.style.display !== 'none') {
                    loadContactDrawerData(phone, chatId);
                }

                let transferBtnHtml = '';
                if (CAN_TRANSFER) {
                    transferBtnHtml = `
                    <button class="omni-transfer-btn" onclick="openTransferModal()" title="Transferir Atendimento">
                        <span>🔄</span> Transferir
                    </button>`;
                }

                const actionsBox = document.getElementById('header-actions');
                if (!isContactTab && chatId > 0) {
                    actionsBox.innerHTML = `
                    <button class="omni-finish-btn" onclick="closeActiveChat(${chatId})" title="Encerrar Atendimento">
                        <span style="font-size: 1.1em;">📵</span> Encerrar
                    </button>
                    ${transferBtnHtml}
                    `;
                } else if (isContactTab) {
                    actionsBox.innerHTML = `
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:0.78rem; color:#64748b; font-weight:600;">👤 Resp: ${escapeHtml(technicianName)}</span>
                            ${transferBtnHtml}
                        </div>
                    `;
                } else {
                    actionsBox.innerHTML = '';
                }

                document.getElementById('message-input').disabled = false;
                document.getElementById('send-btn').disabled = false;

                renderChatList(allLoadedChats);
                loadMessages(isContactTab);
            }

            let chatToClose = 0;
            function closeActiveChat(chatId) {
                chatToClose = chatId;
                document.getElementById('close-chat-modal').style.display = 'flex';
            }

            async function submitCloseChat() {
                if (!chatToClose) return;
                
                const btn = document.getElementById('close-chat-confirm-btn');
                btn.innerText = 'Encerrando...';
                btn.disabled = true;

                const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                
                const formData = new FormData();
                formData.append('chat_id', chatToClose);
                const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/close.php`, {
                    method: 'POST',
                    body: formData
                });

                btn.innerText = 'Sim, Encerrar';
                btn.disabled = false;
                document.getElementById('close-chat-modal').style.display = 'none';

                if (data.success) {
                    closeContactDrawer();
                    activeChatId = 0;
                    activePhoneNumber = '';
                    document.getElementById('main-chat-header').style.display = 'none';
                    document.getElementById('messages-box').innerHTML = `
                        <div class="omni-divider-badge">Atendimento encerrado com sucesso</div>
                    `;
                    document.getElementById('message-input').disabled = true;
                    document.getElementById('send-btn').disabled = true;
                    loadChats();
                } else {
                    alert(data.error || 'Erro ao encerrar atendimento');
                }
            }

            async function loadMessages(isContactTab = false) {
                if (!activeChatId && !activePhoneNumber) return;
                const url = isContactTab 
                    ? `${rootDoc}/plugins/whatsappsimples/ajax/messages.php?phone_number=${encodeURIComponent(activePhoneNumber)}`
                    : `${rootDoc}/plugins/whatsappsimples/ajax/messages.php?chat_id=${activeChatId}`;

                const data = await safeFetchJson(url);
                const box = document.getElementById('messages-box');

                if (!data.messages || data.messages.length === 0) {
                    box.innerHTML = '<div style="margin:auto; color:#94a3b8; font-size:0.85rem;">Sem mensagens registradas</div>';
                    return;
                }

                if (box.innerHTML.includes('Sem mensagens registradas')) {
                    box.innerHTML = '';
                }

                if (!box.querySelector('.omni-divider-badge')) {
                    box.insertAdjacentHTML('afterbegin', '<div class="omni-divider-badge">Atendimento Iniciado</div>');
                }

                // Sincroniza exclusões: remove nós que não existem mais em data.messages
                const returnedIds = new Set(data.messages.map(m => String(m.id)));
                box.querySelectorAll('.omni-bubble[data-msg-id], .omni-system-badge[data-msg-id]').forEach(node => {
                    const msgId = node.getAttribute('data-msg-id');
                    if (msgId && !returnedIds.has(String(msgId))) {
                        const row = node.closest('.omni-msg-row') || node;
                        row.remove();
                    }
                });

                let addedNew = false;

                data.messages.forEach(m => {
                    const existingNode = box.querySelector(`[data-msg-id="${m.id}"]`);
                    if (existingNode) {
                        // Sincroniza edições: se o texto mudou no banco, atualiza no DOM
                        const currentText = decodeURIComponent(existingNode.getAttribute('data-msg-text') || '');
                        const newText = m.message_text || '';
                        if (currentText !== newText) {
                            existingNode.setAttribute('data-msg-text', encodeURIComponent(newText));
                            const textContainer = existingNode.querySelector('.omni-bubble-text');
                            if (textContainer) {
                                textContainer.innerHTML = formatMessageHtml(newText);
                                textContainer.removeAttribute('data-clamped-checked');
                            } else if (newText) {
                                const contentDiv = existingNode.querySelector('.omni-bubble-content');
                                if (contentDiv) {
                                    const newTextDiv = document.createElement('div');
                                    newTextDiv.className = 'omni-bubble-text';
                                    newTextDiv.innerHTML = formatMessageHtml(newText);
                                    contentDiv.appendChild(newTextDiv);
                                }
                            }
                        }
                        return;
                    }

                    addedNew = true;

                    // Divisor de Data: Insere badge de data centralizado sempre que o dia muda
                    const msgDateKey = (m.date_creation || '').substring(0, 10);
                    if (msgDateKey) {
                        const dateBadges = box.querySelectorAll('.omni-date-badge');
                        const lastDateBadge = dateBadges.length > 0 ? dateBadges[dateBadges.length - 1] : null;
                        const lastRenderedDate = lastDateBadge ? lastDateBadge.getAttribute('data-date') : null;

                        if (lastRenderedDate !== msgDateKey) {
                            const dateBadgeNode = document.createElement('div');
                            dateBadgeNode.className = 'omni-date-badge';
                            dateBadgeNode.setAttribute('data-date', msgDateKey);
                            dateBadgeNode.title = formatFullDateTime(m.date_creation).split(' às ')[0];
                            dateBadgeNode.textContent = formatDateBadge(msgDateKey);
                            box.appendChild(dateBadgeNode);
                        }
                    }

                    if (m.sender_type === 'system') {
                        const sysNode = document.createElement('div');
                        sysNode.className = 'omni-system-badge';
                        sysNode.setAttribute('data-msg-id', m.id);
                        sysNode.title = formatFullDateTime(m.date_creation);
                        sysNode.textContent = (m.message_text || '') + (m.date_creation ? ' • ' + formatTime(m.date_creation) : '');
                        box.appendChild(sysNode);
                        return;
                    }
                    let mediaHtml = '';
                    
                    if (m.message_text && m.message_text.trim().startsWith('📎 Arquivo:')) {
                        let lines = m.message_text.trim().split('\n');
                        if (lines.length === 1) {
                            m.message_text = '';
                        } else {
                            m.message_text = lines.slice(1).join('\n');
                        }
                    }

                    function getDocCardHtml(docName, url, warningHtml, sizeBytes) {
                        const lowerName = (docName || '').toLowerCase();
                        let icon = '📄';
                        let typeBadge = 'DOC';
                        let badgeBg = '#64748b';

                        if (lowerName.endsWith('.pdf')) {
                            icon = '📄';
                            typeBadge = 'PDF';
                            badgeBg = '#ef4444';
                        } else if (lowerName.endsWith('.doc') || lowerName.endsWith('.docx')) {
                            icon = '📝';
                            typeBadge = 'WORD';
                            badgeBg = '#2563eb';
                        } else if (lowerName.endsWith('.ppt') || lowerName.endsWith('.pptx')) {
                            icon = '📊';
                            typeBadge = 'PPT';
                            badgeBg = '#ea580c';
                        } else if (lowerName.endsWith('.xls') || lowerName.endsWith('.xlsx') || lowerName.endsWith('.csv')) {
                            icon = '📈';
                            typeBadge = 'EXCEL';
                            badgeBg = '#16a34a';
                        } else if (lowerName.endsWith('.zip') || lowerName.endsWith('.rar') || lowerName.endsWith('.7z')) {
                            icon = '🗜️';
                            typeBadge = 'ZIP';
                            badgeBg = '#8b5cf6';
                        }

                        let sizeStr = '';
                        if (sizeBytes && sizeBytes > 0) {
                            if (sizeBytes < 1024) sizeStr = sizeBytes + ' B';
                            else if (sizeBytes < 1048576) sizeStr = (sizeBytes / 1024).toFixed(1) + ' KB';
                            else sizeStr = (sizeBytes / 1048576).toFixed(1) + ' MB';
                        }

                        return `${warningHtml || ''}
                        <div style="display:flex; flex-direction:column; gap:6px; padding:10px 14px; background:rgba(0,0,0,0.04); border:1px solid rgba(0,0,0,0.08); border-radius:10px; margin-bottom:8px; max-width:320px; box-sizing:border-box;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="font-size:2rem; line-height:1; flex-shrink:0;">${icon}</div>
                                <div style="min-width:0; flex:1;">
                                    <div style="font-weight:600; font-size:0.92rem; color:#1e293b; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(docName)}">
                                        ${escapeHtml(docName)}
                                    </div>
                                    <div style="display:flex; align-items:center; gap:6px; margin-top:2px;">
                                        <span style="font-size:0.68rem; font-weight:700; padding:1px 5px; border-radius:4px; background:${badgeBg}; color:#ffffff; text-transform:uppercase;">${typeBadge}</span>
                                        ${sizeStr ? `<span style="font-size:0.75rem; color:#64748b; font-weight:500;">${sizeStr}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div style="border-top:1px solid rgba(0,0,0,0.06); padding-top:6px; margin-top:2px; display:flex; justify-content:flex-end;">
                                <a href="${url}" download="${escapeHtml(docName)}" target="_blank" rel="noopener noreferrer" style="text-decoration:none; color:#0284c7; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                                    📥 Baixar Arquivo
                                </a>
                            </div>
                        </div>`;
                    }

                    if (m.media_url && m.media_url.startsWith('data:')) {
                        if (m.media_url.startsWith('data:image/')) {
                            mediaHtml = `<img src="${m.media_url}" style="max-width: 100%; max-height: 250px; border-radius: 8px; margin-bottom: 8px; cursor: zoom-in;" alt="Imagem" onclick="openLightbox(this.src)" /><br>`;
                        } else if (m.media_url.startsWith('data:video/')) {
                            mediaHtml = `<video src="${m.media_url}" controls preload="metadata" playsinline style="max-width: 100%; max-height: 250px; border-radius: 8px; margin-bottom: 8px;"></video><br>`;
                        } else if (m.media_url.startsWith('data:audio/')) {
                            mediaHtml = buildAudioPlayerHtml(getAudioBlobUrl(m.media_url), 'data_' + m.id);
                        } else {
                            let docName = 'documento';
                            let extraCaption = '';
                            if (m.message_text) {
                                const match = m.message_text.match(/📎\s*Arquivo:\s*([^\r\n]+)/);
                                if (match) {
                                    docName = match[1].trim();
                                    const lines = m.message_text.split('\n');
                                    lines.shift();
                                    extraCaption = lines.join('\n').trim();
                                }
                            }
                            mediaHtml = getDocCardHtml(docName, m.media_url, '', m.media_size);
                            m.displayCaption = extraCaption;
                        }
                    } else if (m.media_url && m.media_url.startsWith('doc_')) {
                        const filename = m.media_url.substring(4);
                        const fullUrl = `${rootDoc}/plugins/whatsappsimples/front/media.php?id=${m.id}&file=${filename}`;
                        
                        let warningHtml = '';
                        if (m.media_status === 'incomplete') {
                            warningHtml = `<div style="color: #ef4444; font-size: 0.75rem; font-weight: bold; margin-bottom: 4px;">⚠️ Mídia Incompleta (Cortada na Origem)</div>`;
                        }

                        if (filename.endsWith('.jpg') || filename.endsWith('.png') || filename.endsWith('.jpeg')) {
                            mediaHtml = `${warningHtml}<img src="${fullUrl}" style="max-width: 100%; max-height: 250px; border-radius: 8px; margin-bottom: 8px; cursor: zoom-in;" alt="Imagem" onclick="openLightbox(this.src)" /><br>`;
                        } else if (filename.endsWith('.mp4')) {
                            mediaHtml = `${warningHtml}<video src="${fullUrl}" controls preload="metadata" playsinline style="max-width: 100%; max-height: 250px; border-radius: 8px; margin-bottom: 8px;"></video><br>`;
                        } else if (filename.endsWith('.ogg') || filename.endsWith('.m4a') || filename.endsWith('.mp3') || filename.endsWith('.webm')) {
                            mediaHtml = warningHtml + buildAudioPlayerHtml(fullUrl, 'doc_' + m.id);
                        } else {
                            let docName = '';
                            let extraCaption = '';
                            if (m.message_text) {
                                const match = m.message_text.match(/📎\s*Arquivo:\s*([^\r\n]+)/);
                                if (match) {
                                    docName = match[1].trim();
                                    const lines = m.message_text.split('\n');
                                    lines.shift();
                                    extraCaption = lines.join('\n').trim();
                                }
                            }
                            if (!docName) {
                                const cleanBase = filename.split('/').pop();
                                const parts = cleanBase.split('_');
                                if (parts.length > 1) {
                                    parts.shift();
                                    docName = parts.join('_');
                                } else {
                                    docName = cleanBase;
                                }
                            }

                            mediaHtml = getDocCardHtml(docName, fullUrl, warningHtml, m.media_size);
                            m.displayCaption = extraCaption;
                        }
                    }

                    const bubbleText = (m.displayCaption !== undefined) ? m.displayCaption : (m.message_text || '');

                    const msgHtml = `
                        <div class="omni-msg-row" data-wuid="${m.message_id || ''}" style="width: 100%; max-width: 100%; min-width: 0; display: flex; flex-direction: column; padding: 2px 4px; box-sizing: border-box; border-radius: 4px;">
                            <div class="omni-bubble ${m.sender_type} ${m.is_internal ? 'omni-msg-internal' : ''}" data-msg-id="${m.id}" data-wuid="${m.message_id || ''}" data-sender-type="${m.sender_type}" data-msg-text="${encodeURIComponent(m.message_text || '')}" data-media-url="${encodeURIComponent(m.media_url || '')}" style="position:relative;">
                                <div class="omni-msg-menu-btn" onclick="openMsgMenu(event, this)">⌄</div>
                                <div class="omni-bubble-sender">
                                    <span>${escapeHtml(m.sender_name || '')}</span>
                                </div>
                                <div class="omni-bubble-content">${mediaHtml ? `<div class="omni-bubble-media" style="margin-bottom:4px;">${mediaHtml}</div>` : ''}${bubbleText ? `<div class="omni-bubble-text">${formatMessageHtml(bubbleText)}</div>` : ''}</div>
                                <div class="omni-bubble-time" title="${formatFullDateTime(m.date_creation)}">${formatTime(m.date_creation)}${(!m.is_internal && (m.sender_type === 'attendant' || m.sender_type === 'agent')) ? '<span class="omni-check">✓✓</span>' : ''}</div>
                            </div>
                        </div>
                    `;
                    box.insertAdjacentHTML('beforeend', msgHtml);
                });

                applyMessageClamping(box);

                if (addedNew) {
                    box.scrollTop = box.scrollHeight;
                }
            }

            function applyMessageClamping(container) {
                if (!container) return;
                const textNodes = container.querySelectorAll('.omni-bubble-text:not([data-clamped-checked])');
                textNodes.forEach(node => {
                    node.setAttribute('data-clamped-checked', '1');
                    // Altura equivalente a ~6 linhas (125px). Se ultrapassar 130px, ativa o limitador
                    if (node.scrollHeight > 130) {
                        node.classList.add('omni-clamped');
                        const btn = document.createElement('div');
                        btn.className = 'omni-read-more-btn';
                        btn.innerHTML = '... Ler mais ▾';
                        btn.onclick = function(e) {
                            e.stopPropagation();
                            toggleReadMore(this, node);
                        };
                        node.after(btn);
                    }
                });
            }

            window.toggleReadMore = function(btn, node) {
                if (!node) {
                    const bubble = btn.closest('.omni-bubble');
                    if (bubble) node = bubble.querySelector('.omni-bubble-text');
                }
                if (!node) return;

                if (node.classList.contains('omni-clamped')) {
                    node.classList.remove('omni-clamped');
                    btn.innerHTML = 'Mostrar menos ▴';
                } else {
                    node.classList.add('omni-clamped');
                    btn.innerHTML = '... Ler mais ▾';
                }
            };

            let currentQuote = null;
            let currentEditingMsg = null;
            let savedDraftBeforeEdit = '';

            function showQuotePreview(wuid, name, text) {
                cancelEditMode();
                currentQuote = { wuid: wuid, name: name, text: text };
                document.getElementById('quote-preview-container').style.display = 'block';
                document.getElementById('quote-preview-name').innerText = name;
                document.getElementById('quote-preview-text').innerText = text;
                checkInputState();
            }

            window.clearQuotePreview = function() {
                currentQuote = null;
                const container = document.getElementById('quote-preview-container');
                if (container) container.style.display = 'none';
                checkInputState();
            };

            window.cancelEditMode = function() {
                if (!currentEditingMsg) return;
                currentEditingMsg = null;
                const preview = document.getElementById('edit-preview-container');
                if (preview) preview.style.display = 'none';
                const input = document.getElementById('message-input');
                if (input) {
                    input.value = savedDraftBeforeEdit || '';
                    savedDraftBeforeEdit = '';
                    autoResizeInput(input);
                }
                checkInputState();
            };

            let isInternalNoteMode = false;
            window.toggleInternalNoteMode = function() {
                isInternalNoteMode = !isInternalNoteMode;
                const input = document.getElementById('message-input');
                const btn = document.getElementById('internal-note-btn');
                
                if (isInternalNoteMode) {
                    input.style.backgroundColor = '#fef08a';
                    input.style.color = '#854d0e';
                    input.style.borderColor = '#fde047';
                    input.placeholder = 'Digite uma NOTA INTERNA (invisível para o cliente)...';
                } else {
                    input.style.backgroundColor = '';
                    input.style.color = '';
                    input.style.borderColor = '';
                    input.placeholder = 'Digite uma mensagem (Shift + Enter quebra linha)...';
                }
                input.focus();
            };

            async function sendCurrentMessage() {
                const input = document.getElementById('message-input');
                let text = input.value.trim();
                
                if (isInternalNoteMode) {
                    text = '[NOTA INTERNA] ' + text;
                }

                if ((!text && stagedFiles.length === 0) || (!activeChatId && !activePhoneNumber)) return;

                if (isContactTabActive && activeChatOwnerId > 0 && activeChatOwnerId !== currentUserId) {
                    alert('Você está visualizando o Histórico deste Contato e este atendimento está atribuído a OUTRO atendente.\n\nPara poder enviar mensagens, você precisa assumir a propriedade deste chat clicando no botão "Transferir" no cabeçalho e transferindo para o seu nome!');
                    openTransferModal();
                    return;
                }

                // Bloqueia o input durante envio para evitar duplicação
                document.getElementById('send-btn').disabled = true;
                input.disabled = true;

                input.value = '';
                input.style.height = '38px'; // Reset height after send

                const metaCsrf = document.querySelector('meta[property="glpi:csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
                const csrfToken = (typeof CFG_GLPI !== 'undefined' && CFG_GLPI.csrf_token) ? CFG_GLPI.csrf_token : (metaCsrf ? metaCsrf.content : '');
                
                let hasError = false;

                if (stagedFiles.length > 0) {
                    for (let i = 0; i < stagedFiles.length; i++) {
                        const formData = new FormData();
                        formData.append('chat_id', activeChatId || 0);
                        formData.append('phone_number', activePhoneNumber || '');
                        // A legenda vai apenas no primeiro arquivo, se houver
                        if (i === 0 && text) {
                            formData.append('text', text);
                            text = ''; // clear so we don't send it again
                        }
                        formData.append('file', stagedFiles[i]);
                        const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/send.php`, {
                            method: 'POST',
                            body: formData
                        });

                        if (!data.success) {
                            alert('Erro ao enviar o arquivo ' + stagedFiles[i].name + ': ' + (data.error || 'Falha desconhecida'));
                            hasError = true;
                        }
                    }
                } 
                
                if (text) {
                    if (currentQuote) {
                        const quoteJson = JSON.stringify(currentQuote);
                        text = `[QUOTE:${quoteJson}]\n` + text;
                    }
                    const formData = new FormData();
                    formData.append('chat_id', activeChatId || 0);
                    formData.append('phone_number', activePhoneNumber || '');
                    formData.append('text', text);
                    const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/send.php`, {
                        method: 'POST',
                        body: formData
                    });

                    if (!data.success) {
                        alert('Erro ao enviar: ' + (data.error || 'Falha desconhecida'));
                        hasError = true;
                    }
                }

                document.getElementById('send-btn').disabled = false;
                input.disabled = false;
                input.focus();

                if (!hasError) {
                    clearSelectedFile();
                    clearQuotePreview();
                    if (isInternalNoteMode) toggleInternalNoteMode();
                    if (currentTab === 'queue') {
                        const mineBtn = document.querySelector('.omni-tab-btn[onclick="switchTab(\'mine\', this)"]');
                        switchTab('mine', mineBtn, true);
                    } else {
                        loadMessages(isContactTabActive);
                        loadChats();
                    }
                } else {
                    // Recarrega para mostrar as que passaram
                    loadMessages(isContactTabActive);
                    loadChats();
                }
                checkInputState();
            }

            function clearSelectedFile() {
                stagedFiles = [];
                document.getElementById('file-input').value = '';
                renderStagedFiles();
            }

            function removeStagedFile(index) {
                stagedFiles.splice(index, 1);
                renderStagedFiles();
            }

            function renderStagedFiles() {
                const container = document.getElementById('file-preview-container');
                const list = document.getElementById('file-preview-list');
                
                if (stagedFiles.length === 0) {
                    container.style.display = 'none';
                    list.innerHTML = '';
                    checkInputState();
                    return;
                }
                
                container.style.display = 'block';
                list.innerHTML = stagedFiles.map((f, i) => `
                    <div style="display: flex; align-items: center; justify-content: space-between; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; margin-bottom: 4px;">
                        <span style="font-size: 0.8rem; font-weight: 600; color: #334155; max-width: 90%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">📎 ${f.name}</span>
                        <span style="cursor: pointer; color: #ef4444; font-weight: bold; font-size: 1.1rem; line-height: 1;" onclick="removeStagedFile(${i})" title="Remover">&times;</span>
                    </div>
                `).join('');
                checkInputState();
            }

            function uploadSelectedFile(inputEl) {
                if (!inputEl.files || inputEl.files.length === 0) return;
                if (!activeChatId && !activePhoneNumber) {
                    alert('Selecione uma conversa antes de anexar arquivos.');
                    inputEl.value = '';
                    return;
                }
                
                for(let i = 0; i < inputEl.files.length; i++) {
                    stagedFiles.push(inputEl.files[i]);
                }
                
                renderStagedFiles();
                inputEl.value = ''; // Limpa o input para poder selecionar mais depois
            }

            async function toggleOmniPopover(id) {
                const pop = document.getElementById(id);
                if (pop.style.display === 'block') {
                    pop.style.display = 'none';
                } else {
                    document.querySelectorAll('.omni-popover').forEach(p => p.style.display = 'none');
                    pop.style.display = 'block';
                }
            }

            function closeAllPopovers() {
                document.querySelectorAll('.omni-popover').forEach(p => p.style.display = 'none');
            }

            function insertEmoji(emoji) {
                const input = document.getElementById('message-input');
                input.value += emoji;
                input.focus();
                closeAllPopovers();
            }

            function insertCanned(text) {
                const input = document.getElementById('message-input');
                input.value = text;
                input.focus();
                closeAllPopovers();
            }

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.omni-input-footer') && !e.target.closest('.omni-msg-menu-btn')) {
                    closeAllPopovers();
                }
                if (currentMsgMenu && !e.target.closest('#msg-dropdown')) {
                    currentMsgMenu.remove();
                    currentMsgMenu = null;
                }
            });

            function handleKeyPress(e) {
                if (e.key === 'Escape') {
                    if (currentEditingMsg) {
                        e.preventDefault();
                        cancelEditMode();
                        return;
                    }
                }
                if (e.key === 'Enter') {
                    if (e.shiftKey) {
                        // Não faz nada, o comportamento padrão do navegador vai inserir a quebra de linha
                        return;
                    } else {
                        e.preventDefault();
                        if (currentEditingMsg) {
                            submitMessageEdit();
                        } else {
                            sendCurrentMessage();
                        }
                    }
                }
            }

            function autoResizeInput(el) {
                el.style.height = '38px';
                if (el.scrollHeight > 38) {
                    el.style.height = Math.min(el.scrollHeight, 114) + 'px';
                }
            }

            function checkInputState() {
                const input = document.getElementById('message-input');
                const btn = document.getElementById('send-btn');
                
                if (currentEditingMsg) {
                    btn.innerHTML = '✔️';
                    btn.title = 'Salvar alteração (Enter)';
                    btn.style.backgroundColor = '#10b981';
                    btn.style.color = '#ffffff';
                    btn.onclick = submitMessageEdit;
                    return;
                }

                btn.style.backgroundColor = '';
                btn.style.color = '';

                const hasText = input.value.trim().length > 0;
                const hasFiles = stagedFiles.length > 0;
                
                if (hasText || hasFiles) {
                    btn.innerHTML = '✈️';
                    btn.title = 'Enviar';
                    btn.onclick = sendCurrentMessage;
                } else {
                    btn.innerHTML = '🎤';
                    btn.title = 'Gravar Áudio';
                    btn.onclick = toggleAudioRecording;
                }
            }

            let mediaRecorder = null;
            let audioChunks = [];
            let isRecording = false;
            let recordTimer = null;
            let recordSeconds = 0;

            async function toggleAudioRecording() {
                if (!activeChatId && !activePhoneNumber) return;
                
                const btn = document.getElementById('send-btn');
                const input = document.getElementById('message-input');
                
                if (!isRecording) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        
                        let recordOptions = undefined;
                        let audioExt = 'webm';
                        let audioMime = 'audio/webm';
                        if (typeof MediaRecorder.isTypeSupported === 'function') {
                            if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                                recordOptions = { mimeType: 'audio/ogg;codecs=opus' };
                                audioExt = 'ogg';
                                audioMime = 'audio/ogg;codecs=opus';
                            } else if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                                recordOptions = { mimeType: 'audio/webm;codecs=opus' };
                                audioExt = 'webm';
                                audioMime = 'audio/webm;codecs=opus';
                            } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                                recordOptions = { mimeType: 'audio/webm' };
                                audioExt = 'webm';
                                audioMime = 'audio/webm';
                            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                                recordOptions = { mimeType: 'audio/mp4' };
                                audioExt = 'mp4';
                                audioMime = 'audio/mp4';
                            }
                        }

                        mediaRecorder = recordOptions ? new MediaRecorder(stream, recordOptions) : new MediaRecorder(stream);
                        
                        mediaRecorder.ondataavailable = e => {
                            if (e.data.size > 0) audioChunks.push(e.data);
                        };
                        
                        mediaRecorder.onstop = async () => {
                            const actualMime = mediaRecorder.mimeType || audioMime;
                            const audioBlob = new Blob(audioChunks, { type: actualMime });
                            audioChunks = [];
                            
                            clearInterval(recordTimer);
                            input.placeholder = "Digite uma mensagem (Shift + Enter quebra linha)...";
                            btn.innerHTML = '🎤';
                            btn.style.color = '';
                            btn.style.background = '';
                            btn.style.animation = '';
                            
                            const formData = new FormData();
                            formData.append('chat_id', activeChatId || 0);
                            formData.append('phone_number', activePhoneNumber || '');
                            formData.append('is_ptt', '1');
                            formData.append('file', audioBlob, 'audio.' + audioExt);
                            
                            btn.disabled = true;
                            const data = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/send.php`, {
                                method: 'POST',
                                body: formData
                            });
                            
                            btn.disabled = false;
                            
                            if (!data || !data.success) {
                                alert('Erro ao enviar áudio: ' + (data?.error || 'Falha desconhecida'));
                            } else {
                                if (currentTab === 'queue') {
                                    const mineBtn = document.querySelector('.omni-tab-btn[onclick="switchTab(\'mine\', this)"]');
                                    switchTab('mine', mineBtn, true);
                                } else {
                                    loadMessages(isContactTabActive);
                                    loadChats();
                                }
                            }
                            checkInputState();
                        };
                        
                        audioChunks = [];
                        mediaRecorder.start();
                        isRecording = true;
                        
                        btn.innerHTML = '🛑';
                        btn.style.color = '#fff';
                        btn.style.background = '#ef4444';
                        btn.style.animation = 'pulse 1.5s infinite';
                        recordSeconds = 0;
                        
                        input.placeholder = "Gravando áudio... 0:00";
                        input.disabled = true;
                        
                        recordTimer = setInterval(() => {
                            recordSeconds++;
                            const m = Math.floor(recordSeconds / 60);
                            const s = recordSeconds % 60;
                            input.placeholder = `Gravando áudio... ${m}:${s < 10 ? '0' : ''}${s}`;
                        }, 1000);
                        
                    } catch (err) {
                        console.error("Audio error: ", err);
                        if (!window.isSecureContext) {
                            alert("⚠️ Segurança do Navegador: Não foi possível acessar o microfone porque você está acessando via HTTP (Não Seguro). O navegador bloqueia o uso de microfone, câmera e área de transferência em conexões sem HTTPS. \n\nPara enviar áudio, você precisará acessar o GLPI via HTTPS ou configurar o navegador para permitir este IP local.");
                        } else {
                            alert("Não foi possível acessar o microfone. Verifique as permissões do navegador (o ícone de cadeado ao lado do endereço web).");
                        }
                    }
                } else {
                    isRecording = false;
                    input.disabled = false;
                    mediaRecorder.stop();
                    mediaRecorder.stream.getTracks().forEach(track => track.stop());
                }
            }

            function getInitials(name) {
                if (!name) return 'C';
                const parts = name.trim().split(' ');
                if (parts.length >= 2) {
                    return (parts[0][0] + parts[1][0]).toUpperCase();
                }
                return name.substring(0, 2).toUpperCase();
            }

            function formatTime(dateStr) {
                if (!dateStr) return '';
                const parts = dateStr.split(' ');
                if (parts.length === 2) {
                    return parts[1].substring(0, 5);
                }
                return dateStr;
            }

            function parseSqlDate(dateStr) {
                if (!dateStr) return null;
                const parts = dateStr.split(' ');
                if (parts.length >= 1) {
                    const dParts = parts[0].split('-');
                    if (dParts.length === 3) {
                        const y = parseInt(dParts[0], 10);
                        const m = parseInt(dParts[1], 10) - 1;
                        const d = parseInt(dParts[2], 10);
                        let h = 0, min = 0, s = 0;
                        if (parts[1]) {
                            const tParts = parts[1].split(':');
                            h = parseInt(tParts[0], 10) || 0;
                            min = parseInt(tParts[1], 10) || 0;
                            s = parseInt(tParts[2], 10) || 0;
                        }
                        return new Date(y, m, d, h, min, s);
                    }
                }
                const d = new Date(dateStr);
                return isNaN(d.getTime()) ? null : d;
            }

            function formatDateBadge(dateStr) {
                const dateObj = parseSqlDate(dateStr);
                if (!dateObj) return dateStr || '';

                const now = new Date();
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const msgDay = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());

                const diffTime = today.getTime() - msgDay.getTime();
                const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays <= 0) {
                    return 'Hoje';
                } else if (diffDays === 1) {
                    return 'Ontem';
                } else if (diffDays > 1 && diffDays < 7) {
                    const daysOfWeek = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                    return daysOfWeek[msgDay.getDay()];
                } else {
                    const dd = String(msgDay.getDate()).padStart(2, '0');
                    const mm = String(msgDay.getMonth() + 1).padStart(2, '0');
                    return `${dd}/${mm}/${msgDay.getFullYear()}`;
                }
            }

            function formatCardTime(dateStr) {
                const dateObj = parseSqlDate(dateStr);
                if (!dateObj) return formatTime(dateStr);

                const now = new Date();
                const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const msgDay = new Date(dateObj.getFullYear(), dateObj.getMonth(), dateObj.getDate());

                const diffTime = today.getTime() - msgDay.getTime();
                const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

                if (diffDays <= 0) {
                    return formatTime(dateStr);
                } else if (diffDays === 1) {
                    return 'Ontem';
                } else if (diffDays > 1 && diffDays < 7) {
                    const daysOfWeek = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                    return daysOfWeek[msgDay.getDay()];
                } else {
                    const dd = String(msgDay.getDate()).padStart(2, '0');
                    const mm = String(msgDay.getMonth() + 1).padStart(2, '0');
                    return `${dd}/${mm}/${msgDay.getFullYear()}`;
                }
            }

            function formatFullDateTime(dateStr) {
                const dateObj = parseSqlDate(dateStr);
                if (!dateObj) return dateStr || '';
                const dd = String(dateObj.getDate()).padStart(2, '0');
                const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
                const yyyy = dateObj.getFullYear();
                const hh = String(dateObj.getHours()).padStart(2, '0');
                const min = String(dateObj.getMinutes()).padStart(2, '0');
                const ss = String(dateObj.getSeconds()).padStart(2, '0');
                return `${dd}/${mm}/${yyyy} às ${hh}:${min}:${ss}`;
            }

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/\n/g, "<br>");
            }

            window.scrollToMessage = function(wuid) {
                if (!wuid) return;
                const row = document.querySelector(`.omni-msg-row[data-wuid="${wuid}"]`);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    const originalBg = row.style.backgroundColor;
                    const originalTransition = row.style.transition;
                    
                    row.style.transition = 'none';
                    row.style.backgroundColor = 'rgba(14, 165, 233, 0.2)'; 
                    
                    void row.offsetWidth; 
                    
                    row.style.transition = 'background-color 3s ease-out';
                    row.style.backgroundColor = originalBg || '';
                    
                    setTimeout(() => {
                        if (row.style.backgroundColor === originalBg || row.style.backgroundColor === '') {
                            row.style.transition = originalTransition || '';
                        }
                    }, 3000);
                } else {
                    alert('Mensagem original não encontrada na tela (pode estar em um histórico mais antigo).');
                }
            };

            function formatMessageHtml(str) {
                if (!str) return '';

                const trimmedStr = str.trim();
                let htmlPrefix = '';
                let mainContent = trimmedStr;

                if (mainContent.startsWith('[QUOTE:{')) {
                    let endPos = mainContent.indexOf('}]\n');
                    let endLen = 3;
                    if (endPos === -1) {
                        endPos = mainContent.indexOf('}]\r\n');
                        endLen = 4;
                    }
                    if (endPos !== -1) {
                        const jsonStr = mainContent.substring(7, endPos + 1); // +1 to include }
                        try {
                            const qData = JSON.parse(jsonStr);
                            htmlPrefix = `
                                <div onclick="scrollToMessage('${qData.wuid}')" style="background: rgba(0,0,0,0.06); padding: 6px 10px; border-left: 4px solid #0284c7; border-radius: 4px; margin-bottom: 6px; cursor: pointer; user-select: none; transition: opacity 0.2s;" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">
                                    <div style="font-size: 0.75rem; font-weight: bold; color: #0284c7; margin-bottom: 2px;">${escapeHtml(qData.name)}</div>
                                    <div style="font-size: 0.75rem; color: rgba(0,0,0,0.6); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtml(qData.text)}</div>
                                </div>
                            `;
                            mainContent = mainContent.substring(endPos + endLen);
                        } catch(e) {
                            console.error('Error parsing QUOTE JSON', e);
                        }
                    }
                }

                if (mainContent.startsWith('[VCARD_SHARE:') && mainContent.endsWith(']')) {
                    try {
                        const jsonStr = mainContent.substring(13, mainContent.length - 1);
                        const data = JSON.parse(jsonStr);
                        let html = '<div class="omni-contact-cards-container">';
                        data.contacts.forEach(c => {
                            const initial = getInitials(c.name);
                            html += `
                                <div class="omni-vcard">
                                    <div class="omni-vcard-header">
                                        <div class="omni-vcard-avatar">${initial}</div>
                                        <div class="omni-vcard-info">
                                            <div class="omni-vcard-name">${escapeHtml(c.name)}</div>
                                            <div class="omni-vcard-phone">${escapeHtml(c.phone)}</div>
                                        </div>
                                    </div>
                                    <div class="omni-vcard-actions">
                                        <button class="omni-vcard-btn" onclick="copyContactPhone('${c.phone}')">📋 Copiar</button>
                                        <button class="omni-vcard-btn omni-vcard-btn-primary" onclick="openChat(0, '${escapeJs(c.name)}', '${escapeJs(c.phone)}', true)">💬 Conversar</button>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        return htmlPrefix + html;
                    } catch(e) {
                        console.error('Error parsing VCARD_SHARE:', e);
                    }
                }

                let escaped = escapeHtml(mainContent);
                // Bold: *text*
                escaped = escaped.replace(/\*([^\*]+)\*/g, "<strong>$1</strong>");
                // Italic: _text_
                escaped = escaped.replace(/_([^_]+)_/g, "<em>$1</em>");
                // Strikethrough: ~text~
                escaped = escaped.replace(/~([^~]+)~/g, "<del>$1</del>");
                return htmlPrefix + escaped;
            }

            function copyContactPhone(phone) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(phone);
                    alert('Número copiado: ' + phone);
                } else {
                    prompt('Copie o número abaixo:', phone);
                }
            }

            function escapeJs(str) {
                if (!str) return '';
                return str.replace(/'/g, "\\'");
            }

            // Transfer Logic
            async function openTransferModal() {
                if (!activeChatId && !activePhoneNumber) return;
                const modal = document.getElementById('transfer-modal');
                modal.style.display = 'flex';
                
                if (!transferUsersLoaded) {
                    const select = document.getElementById('transfer-user-select');
                    select.innerHTML = '<option value="0">📥 Fila (Desvincular)</option><option disabled>Carregando técnicos...</option>';
                    
                    const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/users.php`);
                    select.innerHTML = '<option value="0">📥 Fila (Desvincular)</option>';
                    
                    if (res && res.users) {
                        res.users.forEach(u => {
                            const opt = document.createElement('option');
                            opt.value = u.id;
                            opt.textContent = `👤 ${u.name}`;
                            select.appendChild(opt);
                        });
                        transferUsersLoaded = true;
                    } else {
                        select.innerHTML = '<option value="0">📥 Fila (Desvincular)</option><option disabled>Erro ao carregar técnicos</option>';
                    }
                }
            }

            function closeTransferModal() {
                document.getElementById('transfer-modal').style.display = 'none';
            }

            async function submitTransfer() {
                if (!activeChatId && !activePhoneNumber) return;
                
                // If chat isn't initialized yet (just clicking in Contacts before any message), activeChatId might be 0, but we need it.
                // However, in contacts, we fetch history by phone, but usually there's a chat ID if it exists.
                if (activeChatId === 0) {
                    alert('Este chat ainda não existe no banco de dados. Envie uma mensagem primeiro.');
                    return;
                }

                const select = document.getElementById('transfer-user-select');
                const newUserId = select.value;
                const btn = document.getElementById('transfer-submit-btn');
                
                btn.innerText = 'Transferindo...';
                btn.disabled = true;
                
                const formData = new FormData();
                formData.append('chat_id', activeChatId);
                formData.append('user_id', newUserId);
                
                const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/transfer.php`, {
                    method: 'POST',
                    body: formData
                });
                
                btn.innerText = 'Confirmar';
                btn.disabled = false;
                
                if (res && res.success) {
                    closeTransferModal();
                    // Limpa chat ativo da tela
                    activeChatId = 0;
                    activePhoneNumber = '';
                    document.getElementById('main-chat-header').style.display = 'none';
                    document.getElementById('messages-box').innerHTML = `
                        <div style="margin:auto; text-align:center; color:#94a3b8; font-size:0.9rem;">
                            <div style="font-size:3rem; margin-bottom:10px;">💬</div>
                            <div>Chat transferido com sucesso!</div>
                        </div>
                    `;
                    document.getElementById('message-input').disabled = true;
                    document.getElementById('send-btn').disabled = true;
                    
                    loadChats();
                } else {
                    alert(res.error || 'Erro ao transferir chat.');
                }
            }

            function openSettingsModal() {
                document.getElementById('new-chat-phone').value = '55';
                document.getElementById('new-chat-name').value = '';
                document.getElementById('new-chat-error').style.display = 'none';
                
                document.getElementById('settings-modal').style.display = 'flex';
                setTimeout(() => document.getElementById('new-chat-phone').focus(), 100);
            }

            function closeSettingsModal() {
                document.getElementById('settings-modal').style.display = 'none';
            }
            
            function switchSettingsTab(tabId) {
                // Future use when we have more tabs
            }

            async function submitNewChat() {
                const phoneInput = document.getElementById('new-chat-phone').value.trim();
                const nameInput = document.getElementById('new-chat-name').value.trim();
                const errorEl = document.getElementById('new-chat-error');
                const btn = document.getElementById('new-chat-submit-btn');

                if (!phoneInput) {
                    errorEl.innerText = 'O número de telefone é obrigatório.';
                    errorEl.style.display = 'block';
                    return;
                }

                errorEl.style.display = 'none';
                btn.innerText = 'Iniciando...';
                btn.disabled = true;

                const formData = new FormData();
                formData.append('phone_number', phoneInput);
                formData.append('contact_name', nameInput);

                const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/new-chat.php`, {
                    method: 'POST',
                    body: formData
                });

                btn.innerText = 'Iniciar';
                btn.disabled = false;

                if (res && res.success) {
                    closeSettingsModal();
                    // Muda para a aba "Meus Chats" para ver a conversa recém adicionada
                    document.querySelector('.omni-tab-btn[onclick="switchTab(\'mine\', this)"]').click();
                    await loadChats();
                    
                    // Abre o chat recém criado
                    openChat(res.chat_id, res.contact_name, res.phone_number, false, 'Você');
                } else {
                    errorEl.innerText = res.error || 'Erro desconhecido ao iniciar conversa.';
                    errorEl.style.display = 'block';
                }
            }

            function buildAudioPlayerHtml(url, uniqueId) {
                return `
                <div class="omni-custom-audio-player">
                    <audio id="audio-${uniqueId}" src="${url}" preload="metadata" ontimeupdate="updateAudioUI('${uniqueId}')" onloadedmetadata="initAudioUI('${uniqueId}')" onended="resetAudioUI('${uniqueId}')" style="display:none;"></audio>
                    
                    <button type="button" onclick="toggleCustomPlaybackSpeed('${uniqueId}', this)" style="border:none; border-radius:10px; padding:0 6px; font-size:0.72rem; font-weight:700; cursor:pointer; min-width:34px; height:22px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">1,0x</button>
                    
                    <div id="play-btn-${uniqueId}" class="omni-play-btn" onclick="togglePlayPause('${uniqueId}')">
                        ▶
                    </div>
                    
                    <div style="flex:1; min-width:0; display:flex; flex-direction:column; justify-content:center; gap:2px;">
                        <input type="range" id="slider-${uniqueId}" min="0" max="100" value="0" step="0.1" oninput="seekAudio('${uniqueId}', this.value)" class="omni-audio-slider" style="background:rgba(0,0,0,0.15);" />
                        <div style="display:flex; justify-content:flex-start; line-height: 1;">
                            <span id="curr-time-${uniqueId}" class="omni-current-time">0:00</span>
                        </div>
                    </div>
                </div>
                `;
            }

            window.togglePlayPause = function(id) {
                const audio = document.getElementById('audio-' + id);
                const btn = document.getElementById('play-btn-' + id);
                if (!audio || !btn) return;
                if (audio.paused) {
                    audio.play();
                    btn.innerHTML = '⏸';
                } else {
                    audio.pause();
                    btn.innerHTML = '▶';
                }
            };

            window.updateAudioUI = function(id) {
                const audio = document.getElementById('audio-' + id);
                const slider = document.getElementById('slider-' + id);
                const currTime = document.getElementById('curr-time-' + id);
                
                if (audio && audio.duration && isFinite(audio.duration)) {
                    const progress = (audio.currentTime / audio.duration) * 100;
                    if (slider) {
                        slider.value = progress;
                        slider.style.background = `linear-gradient(to right, #0284c7 0%, #0284c7 ${progress}%, rgba(0,0,0,0.15) ${progress}%, rgba(0,0,0,0.15) 100%)`;
                    }
                    if (currTime) currTime.innerText = formatAudioTime(audio.currentTime);
                }
            };

            window.initAudioUI = function(id) {
                const audio = document.getElementById('audio-' + id);
                const slider = document.getElementById('slider-' + id);
                if (slider) {
                    slider.value = 0;
                    slider.style.background = 'rgba(0,0,0,0.15)';
                }
                if (audio && audio.duration && isFinite(audio.duration)) {
                    const currTime = document.getElementById('curr-time-' + id);
                    if (currTime) currTime.innerText = formatAudioTime(audio.duration);
                }
            };

            window.resetAudioUI = function(id) {
                const btn = document.getElementById('play-btn-' + id);
                const slider = document.getElementById('slider-' + id);
                const audio = document.getElementById('audio-' + id);
                const currTime = document.getElementById('curr-time-' + id);
                if (btn) btn.innerHTML = '▶';
                if (slider) {
                    slider.value = 0;
                    slider.style.background = 'rgba(0,0,0,0.15)';
                }
                if (audio && audio.duration && isFinite(audio.duration)) {
                    if (currTime) currTime.innerText = formatAudioTime(audio.duration);
                } else {
                    if (currTime) currTime.innerText = '0:00';
                }
            };

            window.seekAudio = function(id, val) {
                const audio = document.getElementById('audio-' + id);
                const slider = document.getElementById('slider-' + id);
                if (slider) {
                    slider.style.background = `linear-gradient(to right, #0284c7 0%, #0284c7 ${val}%, rgba(0,0,0,0.15) ${val}%, rgba(0,0,0,0.15) 100%)`;
                }
                if (audio && audio.duration && isFinite(audio.duration)) {
                    audio.currentTime = (val / 100) * audio.duration;
                }
            };

            window.formatAudioTime = function(secs) {
                if (!secs || !isFinite(secs)) return '0:00';
                const m = Math.floor(secs / 60);
                const s = Math.floor(secs % 60);
                return m + ':' + (s < 10 ? '0' : '') + s;
            };

            window.toggleCustomPlaybackSpeed = function(id, btn) {
                const audio = document.getElementById('audio-' + id);
                if (audio.playbackRate === 1) {
                    audio.playbackRate = 1.5;
                    btn.innerText = '1,5x';
                } else if (audio.playbackRate === 1.5) {
                    audio.playbackRate = 2;
                    btn.innerText = '2,0x';
                } else {
                    audio.playbackRate = 1;
                    btn.innerText = '1,0x';
                }
            };

            // Menu Contextual da Mensagem
            let currentMsgMenu = null;
            window.openMsgMenu = function(e, btn) {
                e.stopPropagation();
                closeAllPopovers();
                
                if (currentMsgMenu) {
                    currentMsgMenu.remove();
                }

                const bubble = btn.closest('.omni-bubble');
                const id = bubble.getAttribute('data-msg-id');
                const type = bubble.getAttribute('data-sender-type');
                const text = decodeURIComponent(bubble.getAttribute('data-msg-text') || '');
                const mediaUrl = decodeURIComponent(bubble.getAttribute('data-media-url') || '');
                
                const isAttendant = type === 'attendant';
                const hasMedia = mediaUrl && mediaUrl !== 'null' && mediaUrl.length > 0;
                const hasText = text && text !== 'null' && text.length > 0;
                
                let html = '<div class="omni-msg-dropdown" id="msg-dropdown" style="display:block;">';
                
                if (hasText) {
                    html += `<div class="omni-msg-dropdown-item" onclick="copyMsgText(this)" data-text="${encodeURIComponent(text)}">📋 Copiar</div>`;
                }
                html += `<div class="omni-msg-dropdown-item" onclick="replyMsg('${id}', this)" data-text="${encodeURIComponent(text)}">↩️ Responder</div>`;
                
                if (hasMedia) {
                    html += `<div class="omni-msg-dropdown-item" onclick="downloadMedia('${mediaUrl}')">📥 Baixar</div>`;
                }
                
                if (isAttendant) {
                    html += `<div class="omni-msg-dropdown-item" onclick="editMsg('${id}', this)" data-text="${encodeURIComponent(text)}">✏️ Editar</div>`;
                    html += `<div class="omni-msg-dropdown-item" style="color: #ef4444;" onclick="deleteMsg('${id}')">🗑️ Apagar</div>`;
                }
                
                html += '</div>';
                
                document.body.insertAdjacentHTML('beforeend', html);
                currentMsgMenu = document.getElementById('msg-dropdown');
                
                const rect = e.target.getBoundingClientRect();
                currentMsgMenu.style.top = (rect.bottom + window.scrollY) + 'px';
                
                if (rect.right + 150 > window.innerWidth) {
                    currentMsgMenu.style.right = (window.innerWidth - rect.right) + 'px';
                } else {
                    currentMsgMenu.style.left = rect.left + 'px';
                }
            };
            
            window.copyMsgText = function(btn) {
                let text = decodeURIComponent(btn.getAttribute('data-text') || '');
                if (text.startsWith('[QUOTE:{')) {
                    let endPos = text.indexOf('}]\n');
                    let endLen = 3;
                    if (endPos === -1) {
                        endPos = text.indexOf('}]\r\n');
                        endLen = 4;
                    }
                    if (endPos !== -1) {
                        text = text.substring(endPos + endLen);
                    }
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text);
                } else {
                    const textArea = document.createElement("textarea");
                    textArea.value = text;
                    textArea.style.position = "fixed";
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        document.execCommand('copy');
                    } catch (err) {
                        console.error('Falha ao copiar', err);
                    }
                    document.body.removeChild(textArea);
                }
                if(currentMsgMenu) currentMsgMenu.remove();
            };
            
            window.downloadMedia = function(url) {
                if (url.startsWith('doc_')) {
                    const filename = url.substring(4);
                    window.open(`${rootDoc}/plugins/whatsappsimples/front/media.php?file=${filename}`, '_blank');
                } else if (url.startsWith('data:')) {
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'media';
                    a.click();
                }
                if(currentMsgMenu) currentMsgMenu.remove();
            };
            
            window.replyMsg = function(id, btn) {
                cancelEditMode();
                const text = decodeURIComponent(btn.getAttribute('data-text') || '');
                const bubble = document.querySelector(`.omni-bubble[data-msg-id="${id}"]`);
                if (!bubble) return;
                
                const wuid = bubble.getAttribute('data-wuid');
                
                let senderName = 'Contato';
                const nameSpan = bubble.querySelector('.omni-bubble-sender span');
                if (nameSpan && nameSpan.innerText) {
                    senderName = nameSpan.innerText;
                } else if (bubble.classList.contains('attendant')) {
                    senderName = 'Você';
                }

                showQuotePreview(wuid, senderName, text);
                document.getElementById('message-input').focus();
                
                if(currentMsgMenu) currentMsgMenu.remove();
            };

            window.editMsg = function(id, btn) {
                if (currentMsgMenu) {
                    currentMsgMenu.remove();
                    currentMsgMenu = null;
                }

                clearQuotePreview();

                if (isInternalNoteMode) {
                    toggleInternalNoteMode();
                }

                const fullText = decodeURIComponent(btn.getAttribute('data-text') || '');
                let quoteHeader = '';
                let editableText = fullText;

                if (fullText.startsWith('[QUOTE:{')) {
                    let endPos = fullText.indexOf('}]\n');
                    let endLen = 3;
                    if (endPos === -1) {
                        endPos = fullText.indexOf('}]\r\n');
                        endLen = 4;
                    }
                    if (endPos !== -1) {
                        quoteHeader = fullText.substring(0, endPos + endLen);
                        editableText = fullText.substring(endPos + endLen);
                    }
                }

                const input = document.getElementById('message-input');
                if (!currentEditingMsg) {
                    savedDraftBeforeEdit = input.value;
                }

                currentEditingMsg = {
                    id: id,
                    quoteHeader: quoteHeader,
                    originalText: editableText
                };

                const preview = document.getElementById('edit-preview-container');
                const previewText = document.getElementById('edit-preview-original-text');
                if (preview && previewText) {
                    previewText.innerText = editableText;
                    preview.style.display = 'block';
                }

                input.value = editableText;
                autoResizeInput(input);
                checkInputState();
                input.focus();
                input.setSelectionRange(input.value.length, input.value.length);
            };

            async function submitMessageEdit() {
                if (!currentEditingMsg) return;

                const input = document.getElementById('message-input');
                const newText = input.value.trim();

                if (newText === '') {
                    alert('A mensagem não pode ficar vazia.');
                    input.focus();
                    return;
                }

                if (newText === currentEditingMsg.originalText.trim()) {
                    cancelEditMode();
                    return;
                }

                const id = currentEditingMsg.id;
                const quoteHeader = currentEditingMsg.quoteHeader;
                const finalTextForDb = quoteHeader ? (quoteHeader + newText) : newText;

                const btn = document.getElementById('send-btn');
                const originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '⏳';

                const formData = new FormData();
                formData.append('msg_id', id);
                formData.append('text', newText);
                formData.append('db_text', finalTextForDb);

                try {
                    const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/edit-message.php`, {
                        method: 'POST',
                        body: formData
                    });

                    if (res && res.success) {
                        const bubble = document.querySelector(`.omni-bubble[data-msg-id="${id}"]`);
                        if (bubble) {
                            const savedDbText = (res.db_text || finalTextForDb);
                            bubble.setAttribute('data-msg-text', encodeURIComponent(savedDbText));
                            const contentEl = bubble.querySelector('.omni-bubble-content');
                            if (contentEl) {
                                const textEl = contentEl.querySelector('.omni-bubble-text');
                                if (textEl) {
                                    textEl.innerHTML = formatMessageHtml(savedDbText);
                                    textEl.removeAttribute('data-clamped-checked');
                                    textEl.classList.remove('omni-clamped');
                                } else {
                                    contentEl.innerHTML = `<div class="omni-bubble-text">${formatMessageHtml(savedDbText)}</div>`;
                                }
                                const existingBtn = bubble.querySelector('.omni-read-more-btn');
                                if (existingBtn) existingBtn.remove();
                                applyMessageClamping(bubble);
                            }
                        }
                        savedDraftBeforeEdit = '';
                        cancelEditMode();
                    } else {
                        alert((res && res.error) ? res.error : 'Erro ao editar mensagem. Nota: O WhatsApp só permite editar mensagens enviadas nos últimos 15 minutos.');
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    }
                } catch (err) {
                    alert('Falha na comunicação ao tentar editar mensagem.');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            }
            
            window.deleteMsg = async function(id) {
                if(confirm('Tem certeza que deseja apagar esta mensagem para todos?')) {
                    const formData = new FormData();
                    formData.append('msg_id', id);
                    
                    const res = await safeFetchJson(`${rootDoc}/plugins/whatsappsimples/ajax/delete-message.php`, {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (res && res.success) {
                        const bubble = document.querySelector(`.omni-bubble[data-msg-id="${id}"]`);
                        if (bubble) {
                            const row = bubble.closest('.omni-msg-row');
                            if (row) row.remove();
                            else bubble.remove();
                        }
                    } else {
                        alert(res.error || 'Erro ao apagar mensagem.');
                    }
                }
                if(currentMsgMenu) currentMsgMenu.remove();
            };

            // Inicia o app: tempo real via Mercure (SSE) com polling lento como plano B
            let sseConnected = false;
            let sseDebounce = null;

            function scheduleRefresh() {
                clearTimeout(sseDebounce);
                sseDebounce = setTimeout(loadChats, 250);
            }

            function connectRealtime() {
                try {
                    const hubTarget = '<?= \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::getPublicHubUrl() ?>';
                    const hubUrl = (hubTarget.startsWith('http://') || hubTarget.startsWith('https://'))
                        ? new URL(hubTarget)
                        : new URL(hubTarget, window.location.origin);

                    hubUrl.searchParams.append('topic', '<?= \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::getTopic() ?>');
                    hubUrl.searchParams.append('match', '<?= \GlpiPlugin\Whatsappsimples\Service\MercurePublisherService::getTopic() ?>');

                    const es = new EventSource(hubUrl);
                    es.onopen = () => { sseConnected = true; };
                    es.onmessage = () => { scheduleRefresh(); };
                    es.onerror = () => { sseConnected = false; }; // EventSource reconecta sozinho
                } catch (e) {
                    sseConnected = false;
                }
            }

            function pollLoop() {
                if (!document.hidden) loadChats();
                // Sem Mercure: 5s. Com Mercure ativo: apenas uma verificacao de seguranca a cada 60s.
                setTimeout(pollLoop, sseConnected ? 60000 : 5000);
            }

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) loadChats();
            });

            connectRealtime();
            loadChats();
            setTimeout(pollLoop, 5000);
        </script>
        <?php

        \Html::footer();
        return new Response(ob_get_clean());
    }
}
