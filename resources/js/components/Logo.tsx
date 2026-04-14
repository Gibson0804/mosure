import React from 'react';

export default function LogoCenter() {
    return (
        <a href="/" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', height: '46px', color: '#000' }}>
            <img src="/logo.png" width={60} height={60} style={{ marginRight: '2px' }} />
            <span style={{ fontSize: '20px', fontWeight: 'bold' }}>Mosure</span>
        </a>
    );
}