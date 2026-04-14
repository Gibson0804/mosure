import React from 'react';
import {
  FileImageOutlined, FileOutlined, FilePdfOutlined, FileWordOutlined,
  FileExcelOutlined, FileZipOutlined, FileTextOutlined, CodeOutlined,
  VideoCameraOutlined, AudioOutlined, FileUnknownOutlined
} from '@ant-design/icons';

type MediaType = 
  | 'image' 
  | 'video' 
  | 'audio' 
  | 'pdf' 
  | 'word' 
  | 'excel' 
  | 'ppt' 
  | 'archive' 
  | 'code' 
  | 'document';

interface MediaTypeConfig {
  icon: React.ReactNode;
  label: string;
  color: string;
}

// Create icon components with style
const createIcon = (IconComponent: React.ComponentType<any>) => 
  React.createElement(IconComponent, { style: { fontSize: 24 } });

// Create all icon components upfront
const iconMap = {
  image: createIcon(FileImageOutlined as React.ComponentType<any>),
  video: createIcon(VideoCameraOutlined as React.ComponentType<any>),
  audio: createIcon(AudioOutlined as React.ComponentType<any>),
  pdf: createIcon(FilePdfOutlined as React.ComponentType<any>),
  word: createIcon(FileWordOutlined as React.ComponentType<any>),
  excel: createIcon(FileExcelOutlined as React.ComponentType<any>),
  ppt: createIcon(FileOutlined as React.ComponentType<any>),
  archive: createIcon(FileZipOutlined as React.ComponentType<any>),
  code: createIcon(CodeOutlined as React.ComponentType<any>),
  document: createIcon(FileTextOutlined as React.ComponentType<any>),
  default: createIcon(FileUnknownOutlined as React.ComponentType<any>)
};

const MEDIA_TYPES: Record<MediaType | 'default', MediaTypeConfig> = {
  image: { 
    label: '图片', 
    icon: iconMap.image,
    color: '#1890ff' 
  },
  video: { 
    label: '视频', 
    icon: iconMap.video,
    color: '#52c41a' 
  },
  audio: { 
    label: '音频', 
    icon: iconMap.audio,
    color: '#722ed1' 
  },
  pdf: { 
    label: 'PDF', 
    icon: iconMap.pdf,
    color: '#f5222d' 
  },
  word: { 
    label: 'Word', 
    icon: iconMap.word,
    color: '#2f54eb' 
  },
  excel: { 
    label: 'Excel', 
    icon: iconMap.excel,
    color: '#13c2c2' 
  },
  ppt: { 
    label: 'PPT', 
    icon: iconMap.ppt,
    color: '#fa8c16' 
  },
  archive: { 
    label: '压缩包', 
    icon: iconMap.archive,
    color: '#faad14' 
  },
  code: { 
    label: '代码', 
    icon: iconMap.code,
    color: '#722ed1' 
  },
  document: { 
    label: '文档', 
    icon: iconMap.document,
    color: '#595959' 
  },
  default: { 
    label: '未知类型', 
    icon: iconMap.default,
    color: '#8c8c8c' 
  }
};

export const getMediaTypeConfig = (type: string): MediaTypeConfig => {
  return MEDIA_TYPES[type as MediaType] || MEDIA_TYPES.default;
};

export const isImage = (type: string): boolean => type === 'image';
export const isVideo = (type: string): boolean => type === 'video';
export const isAudio = (type: string): boolean => type === 'audio';

export default MEDIA_TYPES;
