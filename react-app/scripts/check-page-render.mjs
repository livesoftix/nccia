import { createServer } from 'vite';
import React from 'react';
import { renderToString } from 'react-dom/server';
import {MemoryRouter} from 'react-router-dom';
const server = await createServer({ server: { middlewareMode: true }, appType: 'custom' });
try {
 const {AuthProvider} = await server.ssrLoadModule('/src/contexts/AuthContext.jsx');
 for (const name of ['EnquiryForm','DsrReportForm','DoLetterForm']) {
  try {
   const {default: Page}=await server.ssrLoadModule(`/src/pages/${name}.jsx`);
   const html=renderToString(React.createElement(MemoryRouter,null,React.createElement(AuthProvider,null,React.createElement(Page))));
   console.log(name, 'rendered', html.length);
  }catch(e){console.error(name,e.stack);process.exitCode=1;}
 }
} finally {await server.close();}
