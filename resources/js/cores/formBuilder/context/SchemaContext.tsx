import { createContext, useContext, useReducer } from 'react';
import React from 'react';
import { GetRandomString } from '../utils/stringUtil';

export type SchemasType = {
    page_id: string,
    page_name: string,
    mold_type: string,
    children: SchemasChildType[]
}

//todo::字段梳理，字段含义和字段命名
export type SchemasChildType = {
    id: string,
    field: string,
    label?: string,
    name?: string,
    type?: string,
    icon?: string,
    input?: boolean,
    length?: number,
    isPick?: boolean,
    componentProps?: any,
    options?: any,
    plainOptions?:any,
    curValue?: any,
    rules?: any,
}

const initialSchemas:SchemasType = {
    "page_id": "root",
    "page_name": "页面",
    "mold_type": "list",
    "children": [
        {
            "label": "文本域1",
            "type": "input",
            "field": "input_0juljn6w",
            "id": "input_0juljn6w"
        },
        {
            "label": "文本域2",
            "type": "input",
            "field": "input_0juljn6w333",
            "id": "input_0juljn6w333"
        }
    ],
};

const SchemaContext = createContext(null);

const SchemaDispatchContext = createContext(null);

type providerProps = {
    children: React.ReactNode,
    initSchemas: SchemasType|null
}

export function SchemasProvider({ children, initSchemas }: providerProps) {
    let initialSchemasCur: SchemasType = initialSchemas;
    if (initSchemas != null) {
        initialSchemasCur = initSchemas;
    }

    const [schemas, dispatch]: [SchemasType, Function]  = useReducer(
        schemaReducer,
        initialSchemasCur
    );

    return (
        <SchemaContext.Provider value={schemas}>
            <SchemaDispatchContext.Provider value={dispatch}>
                {children}
            </SchemaDispatchContext.Provider>
        </SchemaContext.Provider>
    );
}

export function useSchema() : SchemasType{
    // 在 useContext(SchemaContext) 后面添加了 ! 非空断言操作符，这样 TypeScript 就不会报错了，因为我们明确告诉 TypeScript 返回的值不会是 null。
    return useContext(SchemaContext)!;
}

export function useSchemasDispatch(): Function {//todo::这里应该是什么类型
    return useContext(SchemaDispatchContext)!;
}

type ActionType = {
    type: string,
    name: string,
    icon_type: string,
    id: string,
    fromIndex?: number,
    index?: number, //拖动时用
    field?: string,
    value?: string,
    schemas?: SchemasType
}

function schemaReducer(schemas: SchemasType, action: ActionType): SchemasType {
    switch (action.type) {
        case 'added': {

            let randomId = GetRandomString(6);

            let newChild: SchemasChildType = {
                id: action.icon_type + '_' + randomId,
                type: action.icon_type,
                label: action.name,
                field: action.icon_type + '_' + randomId,
            };

            if (['select', 'checkbox', 'radio'].includes(action.icon_type)) {
                newChild.options = [
                    { value: 'A', label: 'A' },
                    { value: 'B', label: 'B' }
                ]
                newChild.plainOptions = 'A,B'
            }
            let updatedChildren = schemas.children
            if (action.index != null) {
                updatedChildren.splice(action.index, 0, newChild);
            } else {
                updatedChildren.push(newChild);
            }

            return {...schemas, children: updatedChildren}

        }
        case 'replace': {
            if(action.schemas) {
                return action.schemas;
            }

            return schemas;
        }
        case 'pick_id': {
            const updatedChildren: Array<SchemasChildType> = schemas.children.map(child => {
                if (child.id == action.id) {
                    // 根据action中的filed对应的自动，修改child中的字段
                    return { ...child, "isPick": true };
                }
                // 根据action中的filed对应的自动，修改child中的字段
                return { ...child, "isPick": false };
            });
            return { ...schemas, children: updatedChildren };
        }
        case 'change_input_place': {

            if(action.fromIndex == null || action.index == null ) {
                return schemas
            }
            let element = schemas.children.splice(action.fromIndex, 1)[0]; // 从索引为 1 的位置开始移除一个元素

            schemas.children.splice(action.index, 0, element); // 在索引为 3 的位置插入元素
            let updatedChildren = schemas.children

            return {...schemas, children: updatedChildren}

        }
        case 'unpick_all': {
            const updatedChildren = schemas.children.map(child => {
                return { ...child, "isPick": false };
            });
            return { ...schemas, children: updatedChildren };
        }
        case 'changed_by_id': {
            const updatedChildren = schemas.children.map(child => {
                if (child.id == action.id) {
                    let updataChild = {...child}
                    if(action.field) {
                        // 在这个示例中，我们使用了类型断言 (updatedChild as any) 来告诉 TypeScript，我们知道 updatedChild 对象中属性的类型是 any，从而避免了报错。
                        (updataChild as any)[action.field] = action.value
                    }
                    // 根据action中的filed对应的自动，修改child中的字段
                    return updataChild
                }
                return child;
            });
            return { ...schemas, children: updatedChildren };
        }
        case 'changed_page_info': {

            // schemas中存在action.field名字的key的时候，修改对应的值
            let res = {...schemas}
            if(action.field) {
                // 在这个示例中，我们使用了类型断言 (updatedChild as any) 来告诉 TypeScript，我们知道 updatedChild 对象中属性的类型是 any，从而避免了报错。
                (res as any)[action.field] = action.value
            }
            // 根据action中的filed对应的自动，修改child中的字段
            return res
        }
        case 'deleted': {
            const filteredChildren = schemas.children.filter(child => child.id !== action.id);
            return { ...schemas, children: filteredChildren };
        }
        default: {
            throw Error('Unknown action: ' + action.type);
        }
    }
}
