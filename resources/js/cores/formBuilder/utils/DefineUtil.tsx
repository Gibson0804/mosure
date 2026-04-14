


export const DataTimeFormat = "YYYY-MM-DD HH:mm:ss"
export const DataFormat = "YYYY-MM-DD"
export const TimeFormat = "HH:mm:ss"

export const UploadPath = '/media/upload'
export const GetUploadResPath  = (file) => {
    return file.response?.data?.url || file.url;
}

export const GetUploadResId  = (file) => {
    return file.response?.data?.id || file.uid;
}
