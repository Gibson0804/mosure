

export interface MoldField {
    field?: string;
    type?: string;
    label?: string;
    title?: string;
  }
  
export interface MoldItem {
    id: number;
    name: string;
    description?: string | null;
    table_name: string;
    mold_type: string;
    fields: MoldField[];
    subject_content: Record<string, unknown>;
    list_show_fields: string[];
    updated_at?: string | null;
  }
  