import { useEffect, useRef } from 'react';
import { message } from 'antd';
import { usePage } from '@inertiajs/react';

interface FlashState {
  status?: string | null;
  type?: 'success' | 'error' | 'info' | 'warning' | null;
}

export default function useFlashMessage() {
  const page = usePage();
  const flash = ((page.props as any)?.flash ?? {}) as FlashState;
  const lastShown = useRef<string | null>(null);

  useEffect(() => {
    if (!flash?.status) {
      return;
    }

    const key = `${flash.type || 'success'}:${flash.status}`;
    if (lastShown.current === key) {
      return;
    }

    lastShown.current = key;
    const level = flash.type && ['success', 'error', 'info', 'warning'].includes(flash.type)
      ? flash.type
      : 'success';

    message[level](flash.status);
  }, [flash?.status, flash?.type]);
}
