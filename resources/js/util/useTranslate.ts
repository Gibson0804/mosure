import { usePage } from '@inertiajs/react';

export type TranslateMap = Record<string, string>;

export const useTranslate = () => {
  const { props } = usePage<{ translate?: TranslateMap }>();
  const translations = props.translate ?? {};

  const _t = (key: string, fallback?: string): string => {
    if (key in translations) {
      return translations[key];
    }
    if (fallback) {
      return fallback;
    }
    return key;
  };

  return _t;
};
