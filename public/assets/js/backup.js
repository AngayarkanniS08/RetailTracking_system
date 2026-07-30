/**
 * backup.js — Enterprise Backup Operations Center Unified Main Entry Point
 * 
 * Architecture Layout:
 * - BackupConstants  : System Enums, Endpoints & Tokens
 * - BackupEventBus   : Decoupled Pub/Sub Event System
 * - BackupLogger     : Audit & Diagnostic Logger
 * - BackupRepository : Data Access with AbortController Support
 * - BackupService    : Pure Business Logic, ViewModels & 7-Day Calendar Heatmaps
 * - BackupRenderer   : DOM Presentation & UI Component Sub-renderers
 * - BackupPoller     : Lifecycle-Aware Polling & Visibility Management
 * - BackupController : Central Application Orchestrator
 */

// Initialize Central Controller Singleton
const backupController = new BackupController();

// Global Window Backward Compatibility Exports for DOM Action Bindings
window.startBackup = () => backupController.startBackup();
window.loadRestoreFiles = () => backupController.loadRestoreFiles();
window.triggerRestoreFile = (id, name) => backupController.triggerRestoreFile(id, name);
window.verifyLatestBackup = () => backupController.verifyLatestBackup();
window.downloadLatestBackup = () => backupController.downloadLatestBackup();
window.connectGoogleDrive = () => backupController.connectGoogleDrive();
window.saveBackupConfig = () => backupController.saveBackupConfig();
window.loadBackupPage = () => backupController.loadBackupPage();
window.toggleConfigPanel = () => backupController.toggleConfigPanel();
window.openConfigPanel = () => backupController.openConfigPanel();
window.backupController = backupController;

// Auto-run on DOM Ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => backupController.loadBackupPage());
} else {
    backupController.loadBackupPage();
}
