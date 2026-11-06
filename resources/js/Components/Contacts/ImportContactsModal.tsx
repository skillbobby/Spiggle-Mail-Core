import React, { useRef } from 'react';
import { UploadCloud, CheckCircle2, AlertCircle, X, FileText } from 'lucide-react';
import { useCsvImport } from '@/hooks/useCsvImport';

interface ImportContactsModalProps {
  readonly isOpen: boolean;
  readonly onClose: () => void;
  readonly onConfirmImport: (file: File) => void;
}

export const ImportContactsModal: React.FC<ImportContactsModalProps> = ({
  isOpen,
  onClose,
  onConfirmImport,
}) => {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { file, isParsing, totalRows, previewRows, error, handleFileSelect, clearFile } =
    useCsvImport();

  if (!isOpen) return null;

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileSelect(e.dataTransfer.files[0]);
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      handleFileSelect(e.target.files[0]);
    }
  };

  const handleSubmit = () => {
    if (!file) return;
    onConfirmImport(file);
    onClose();
  };

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs"
    >
      <div className="w-full max-w-xl rounded-2xl bg-zinc-950 border border-zinc-800 shadow-2xl overflow-hidden text-left">
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-zinc-800/80">
          <div>
            <h2 className="text-base font-semibold text-white">Import Subscribers (CSV)</h2>
            <p className="text-xs text-zinc-400 mt-0.5">
              Supports Substack, beehiiv, Mailchimp, Kit, and custom CSV exports.
            </p>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close modal"
            className="text-zinc-400 hover:text-white p-1 rounded-lg hover:bg-zinc-900 transition"
          >
            <X className="w-4 h-4" aria-hidden="true" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6 space-y-5">
          {!file ? (
            <div
              onDragOver={(e) => e.preventDefault()}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className="border-2 border-dashed border-zinc-800 hover:border-indigo-500/60 rounded-xl p-8 text-center cursor-pointer bg-zinc-900/30 hover:bg-zinc-900/60 transition group"
            >
              <input
                ref={fileInputRef}
                type="file"
                accept=".csv"
                onChange={handleFileChange}
                className="hidden"
              />
              <div className="w-12 h-12 rounded-full bg-zinc-800 flex items-center justify-center mx-auto text-zinc-400 group-hover:text-indigo-400 group-hover:bg-indigo-500/10 transition mb-3">
                <UploadCloud className="w-6 h-6" aria-hidden="true" />
              </div>
              <p className="text-sm font-semibold text-white">Click to upload or drag & drop</p>
              <p className="text-xs text-zinc-500 mt-1">UTF-8 CSV format up to 50MB</p>
            </div>
          ) : (
            <div className="space-y-4">
              {/* File Info Bar */}
              <div className="flex items-center justify-between p-3 rounded-xl bg-zinc-900 border border-zinc-800">
                <div className="flex items-center gap-2.5 truncate">
                  <FileText className="w-5 h-5 text-indigo-400 shrink-0" aria-hidden="true" />
                  <div className="truncate">
                    <p className="text-xs font-semibold text-white truncate">{file.name}</p>
                    <p className="text-[11px] text-zinc-400">
                      {totalRows.toLocaleString()} rows detected
                    </p>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={clearFile}
                  aria-label="Remove file"
                  className="text-zinc-500 hover:text-rose-400 p-1"
                >
                  <X className="w-4 h-4" aria-hidden="true" />
                </button>
              </div>

              {/* Preview Table */}
              {isParsing ? (
                <p className="text-xs text-zinc-400 italic">Analyzing column headers...</p>
              ) : previewRows.length > 0 ? (
                <div className="rounded-xl border border-zinc-800 overflow-hidden">
                  <div className="bg-zinc-900 px-3 py-2 text-[11px] font-semibold text-zinc-400 uppercase tracking-wider border-b border-zinc-800">
                    Sample Data Preview
                  </div>
                  <table className="w-full text-left text-xs text-zinc-300">
                    <thead className="bg-zinc-950 border-b border-zinc-800/60 text-zinc-400">
                      <tr>
                        <th className="px-3 py-2">Email</th>
                        <th className="px-3 py-2">Name</th>
                        <th className="px-3 py-2">Status</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-zinc-800/40">
                      {previewRows.map((row, idx) => (
                        <tr key={idx} className="hover:bg-zinc-900/40">
                          <td className="px-3 py-2 text-white font-mono">{row.email}</td>
                          <td className="px-3 py-2">{row.name || '—'}</td>
                          <td className="px-3 py-2 text-emerald-400">{row.status}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}

              {/* Automatic Hygiene Notice */}
              <div className="flex items-start gap-2 p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs leading-relaxed">
                <CheckCircle2 className="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" aria-hidden="true" />
                <span>
                  <strong>Automated Hygiene Active:</strong> 10,143+ disposable domains, role addresses (e.g. <code>admin@</code>), and past bounces/complaints will be filtered out automatically.
                </span>
              </div>
            </div>
          )}

          {error && (
            <div className="flex items-center gap-2 text-xs text-rose-400 bg-rose-500/10 border border-rose-500/20 p-3 rounded-lg">
              <AlertCircle className="w-4 h-4 shrink-0" aria-hidden="true" />
              <span>{error}</span>
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className="flex items-center justify-end gap-3 px-6 py-4 bg-zinc-900/40 border-t border-zinc-800/80">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium rounded-lg text-zinc-400 hover:text-white transition"
          >
            Cancel
          </button>
          <button
            type="button"
            disabled={!file}
            onClick={handleSubmit}
            className={`px-5 py-2 text-sm font-semibold rounded-lg text-white transition shadow-sm ${
              file
                ? 'bg-indigo-600 hover:bg-indigo-500 shadow-indigo-600/20'
                : 'bg-zinc-800 text-zinc-500 cursor-not-allowed'
            }`}
          >
            Confirm & Import
          </button>
        </div>
      </div>
    </div>
  );
};
