import { useState, useCallback } from 'react';
import Papa from 'papaparse';

export interface ParsedCsvRow {
  readonly email: string;
  readonly name?: string;
  readonly status?: string;
}

export interface UseCsvImportResult {
  readonly file: File | null;
  readonly isParsing: boolean;
  readonly totalRows: number;
  readonly previewRows: readonly ParsedCsvRow[];
  readonly error: string | null;
  readonly handleFileSelect: (selectedFile: File) => void;
  readonly clearFile: () => void;
}

export function useCsvImport(): UseCsvImportResult {
  const [file, setFile] = useState<File | null>(null);
  const [isParsing, setIsParsing] = useState<boolean>(false);
  const [totalRows, setTotalRows] = useState<number>(0);
  const [previewRows, setPreviewRows] = useState<readonly ParsedCsvRow[]>([]);
  const [error, setError] = useState<string | null>(null);

  const handleFileSelect = useCallback((selectedFile: File) => {
    if (!selectedFile.name.endsWith('.csv')) {
      setError('Please choose a valid .csv file');
      return;
    }

    setFile(selectedFile);
    setIsParsing(true);
    setError(null);

    Papa.parse<Record<string, string>>(selectedFile, {
      header: true,
      skipEmptyLines: true,
      preview: 5,
      complete: (results) => {
        const rows: ParsedCsvRow[] = results.data.map((row) => {
          // Normalize common headers across Substack, Mailchimp, Kit, Beehiiv
          const email =
            row['email'] ||
            row['Email'] ||
            row['EMAIL'] ||
            row['Email Address'] ||
            row['email_address'] ||
            '';

          const name =
            row['first_name'] ||
            row['First Name'] ||
            row['name'] ||
            row['Name'] ||
            '';

          return {
            email: email.trim().toLowerCase(),
            name: name.trim(),
            status: row['status'] || 'subscribed',
          };
        }).filter((r) => r.email.includes('@'));

        setPreviewRows(rows);
        setIsParsing(false);
      },
      error: (err) => {
        setError(`Failed to parse CSV: ${err.message}`);
        setIsParsing(false);
      },
    });

    // Also get quick total line count
    const reader = new FileReader();
    reader.onload = () => {
      const text = reader.result as string;
      const lines = text.split('\n').filter((l) => l.trim().length > 0);
      setTotalRows(Math.max(0, lines.length - 1));
    };
    reader.readAsText(selectedFile);
  }, []);

  const clearFile = useCallback(() => {
    setFile(null);
    setPreviewRows([]);
    setTotalRows(0);
    setError(null);
  }, []);

  return {
    file,
    isParsing,
    totalRows,
    previewRows,
    error,
    handleFileSelect,
    clearFile,
  };
}
