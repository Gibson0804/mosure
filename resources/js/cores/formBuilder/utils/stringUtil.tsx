
export function GetRandomString(length: number) {
    var result = '';
    var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    var charactersLength = characters.length;
    for ( var i = 0; i < length; i++ ) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return result;
}


export const TransformChildren = (rawData) => {
    return rawData.map(item => {
        let res =  {
            ...item,
            id: item.type + '_' +GetRandomString(6),
            field: item.field || item.type + '_' +GetRandomString(6),
        }
        if (item.options != undefined) {
            let op = item.options
            res.options = op.map(item => {
                return {
                    label: item,
                    value: item
                }
            })
            res.plainOptions = op.join(',')
        }
        return res
    })
}
